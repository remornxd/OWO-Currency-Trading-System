from fastapi import FastAPI, Request, HTTPException, Depends, BackgroundTasks, WebSocket, Security
from fastapi.templating import Jinja2Templates
from fastapi.staticfiles import StaticFiles
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm
from fastapi.middleware.cors import CORSMiddleware
from jose import JWTError, jwt
from passlib.context import CryptContext
from datetime import datetime, timedelta
from typing import Optional, Dict, List
from pydantic import BaseModel, Field
from bson import ObjectId
import asyncio
import json
from .filters import format_number, format_datetime
from ..models.token_manager import TokenManager
from ..database import get_database
from .auth import (
    get_current_user,
    get_current_admin,
    create_access_token,
    ACCESS_TOKEN_EXPIRE_MINUTES
)
from .models import (
    TokenBase,
    TokenCreate,
    TokenUpdate,
    TokenResponse,
    TokenStats,
    UserCreate,
    UserLogin,
    UserResponse
)

app = FastAPI(title="OWO Token Yönetimi", version="2.0.0")

# CORS ayarları
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Şablonlar ve statik dosyalar
templates = Jinja2Templates(directory="src/web/templates")
templates.env.filters["number_format"] = format_number
templates.env.filters["datetime"] = format_datetime
app.mount("/static", StaticFiles(directory="src/web/static"), name="static")

# Güvenlik ayarları
SECRET_KEY = "your-secret-key-here"
ALGORITHM = "HS256"

pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="token")

# Veritabanı bağlantısı
db = get_database()

# Token yöneticileri
token_managers: Dict[str, TokenManager] = {}

# WebSocket bağlantıları
active_connections: List[WebSocket] = []

async def broadcast_update(data: dict):
    """Tüm bağlı WebSocket istemcilerine güncelleme gönder"""
    for connection in active_connections:
        try:
            await connection.send_json(data)
        except:
            active_connections.remove(connection)

@app.websocket("/ws")
async def websocket_endpoint(websocket: WebSocket):
    await websocket.accept()
    active_connections.append(websocket)
    try:
        while True:
            await websocket.receive_text()
    except:
        active_connections.remove(websocket)

# Auth Routes
@app.post("/api/auth/login", response_model=dict)
async def login(form_data: UserLogin):
    """Kullanıcı girişi"""
    user = await db.users.find_one({"username": form_data.username})
    if not user or not pwd_context.verify(form_data.password, user["password"]):
        raise HTTPException(
            status_code=401,
            detail="Kullanıcı adı veya şifre hatalı!",
        )
    
    access_token_expires = timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
    access_token = create_access_token(
        data={"sub": user["username"], "role": user.get("role", "user")},
        expires_delta=access_token_expires
    )
    return {
        "access_token": access_token,
        "token_type": "bearer",
        "role": user.get("role", "user")
    }

@app.post("/api/auth/register", response_model=UserResponse)
async def register(user_data: UserCreate):
    """Yeni kullanıcı kaydı (Sadece key ile)"""
    # Key kontrolü
    key = await db.keys.find_one({"key": user_data.key, "isUsed": False})
    if not key:
        raise HTTPException(status_code=400, detail="Geçersiz veya kullanılmış key!")
    
    # Kullanıcı adı kontrolü
    existing_user = await db.users.find_one({"username": user_data.username})
    if existing_user:
        raise HTTPException(status_code=400, detail="Bu kullanıcı adı zaten kullanılıyor!")
    
    # Yeni kullanıcı oluştur
    hashed_password = pwd_context.hash(user_data.password)
    user_doc = {
        "username": user_data.username,
        "password": hashed_password,
        "role": "user",
        "package_type": key["packageType"],
        "created_at": datetime.utcnow()
    }
    
    result = await db.users.insert_one(user_doc)
    user_doc["id"] = str(result.inserted_id)
    
    # Key'i kullanıldı olarak işaretle
    await db.keys.update_one(
        {"_id": key["_id"]},
        {
            "$set": {
                "isUsed": True,
                "usedBy": str(result.inserted_id),
                "usedAt": datetime.utcnow()
            }
        }
    )
    
    return UserResponse(**user_doc)

# Web Routes
@app.get("/")
async def home(request: Request):
    """Login sayfası"""
    return templates.TemplateResponse("login.html", {"request": request})

@app.get("/dashboard")
async def dashboard(request: Request, current_user: dict = Depends(get_current_user)):
    """Kullanıcı dashboard"""
    return templates.TemplateResponse(
        "dashboard.html",
        {
            "request": request,
            "user": current_user
        }
    )

@app.get("/admin")
async def admin_panel(request: Request, current_admin: dict = Depends(get_current_admin)):
    """Admin panel"""
    return templates.TemplateResponse(
        "admin.html",
        {
            "request": request,
            "user": current_admin
        }
    )

# API Routes
@app.get("/api/stats", response_model=TokenStats)
async def get_stats(current_user: dict = Depends(get_current_user)):
    """Genel istatistikleri getir"""
    query = {}
    if current_user["role"] != "admin":
        query["currentUser"] = str(current_user["_id"])
    
    stats = {
        "total_tokens": await db.tokens.count_documents(query),
        "active_tokens": await db.tokens.count_documents({**query, "status": "available"}),
        "busy_tokens": await db.tokens.count_documents({**query, "status": "busy"}),
        "banned_tokens": await db.tokens.count_documents({**query, "status": "banned"}),
        "total_owo": sum(token.get("owo_balance", 0) for token in await db.tokens.find(query).to_list(None)),
        "total_messages": sum(token.get("total_messages", 0) for token in await db.tokens.find(query).to_list(None))
    }
    return TokenStats(**stats)

@app.get("/api/stats/chart")
async def get_chart_data(current_user: dict = Depends(get_current_user)):
    """Chart için istatistik verileri"""
    query = {}
    if current_user["role"] != "admin":
        query["currentUser"] = str(current_user["_id"])
    
    # Son 7 günün verilerini al
    end_date = datetime.utcnow()
    start_date = end_date - timedelta(days=7)
    
    pipeline = [
        {
            "$match": {
                **query,
                "created_at": {"$gte": start_date, "$lte": end_date}
            }
        },
        {
            "$group": {
                "_id": {
                    "$dateToString": {
                        "format": "%Y-%m-%d",
                        "date": "$created_at"
                    }
                },
                "total_owo": {"$sum": "$owo_balance"},
                "total_messages": {"$sum": "$total_messages"}
            }
        },
        {"$sort": {"_id": 1}}
    ]
    
    stats = await db.tokens.aggregate(pipeline).to_list(None)
    
    return {
        "labels": [stat["_id"] for stat in stats],
        "datasets": [
            {
                "label": "Toplam OWO",
                "data": [stat["total_owo"] for stat in stats],
                "borderColor": "#4e73df",
                "fill": False
            },
            {
                "label": "Toplam Mesaj",
                "data": [stat["total_messages"] for stat in stats],
                "borderColor": "#1cc88a",
                "fill": False
            }
        ]
    }

@app.get("/api/tokens", response_model=List[TokenResponse])
async def get_tokens(
    skip: int = 0, 
    limit: int = 100,
    status: Optional[str] = None,
    current_user: dict = Depends(get_current_user)
):
    """Token listesini getir"""
    query = {}
    if current_user["role"] != "admin":
        query["currentUser"] = str(current_user["_id"])
    if status:
        query["status"] = status
    
    tokens = await db.tokens.find(query).skip(skip).limit(limit).to_list(None)
    return [TokenResponse(**token) for token in tokens]

@app.post("/api/tokens", response_model=TokenResponse)
async def create_token(
    token_data: TokenCreate,
    background_tasks: BackgroundTasks,
    current_user: dict = Depends(get_current_user)
):
    """Yeni token ekle"""
    # Token formatını kontrol et
    if not token_data.token.strip() or len(token_data.token) < 50:
        raise HTTPException(status_code=400, detail="Geçersiz token formatı!")

    # Kanal ID'sini kontrol et
    if not token_data.channel_id.strip().isdigit():
        raise HTTPException(status_code=400, detail="Geçersiz kanal ID!")

    token_doc = {
        "token": token_data.token,
        "channel_id": token_data.channel_id,
        "status": "available",
        "owo_balance": 0,
        "total_messages": 0,
        "created_at": datetime.utcnow(),
        "last_update": datetime.utcnow(),
        "is_running": False,
        "captcha_detected": False
    }
    
    result = await db.tokens.insert_one(token_doc)
    token_doc["id"] = str(result.inserted_id)
    
    # WebSocket bildirimi gönder
    background_tasks.add_task(broadcast_update, {
        "type": "token_created",
        "data": token_doc
    })
    
    return TokenResponse(**token_doc)

@app.get("/api/tokens/{token_id}", response_model=TokenResponse)
async def get_token(token_id: str, current_user: dict = Depends(get_current_user)):
    """Token detaylarını getir"""
    token = await db.tokens.find_one({"_id": ObjectId(token_id)})
    if not token:
        raise HTTPException(status_code=404, detail="Token bulunamadı!")
    
    token["id"] = str(token["_id"])
    return TokenResponse(**token)

@app.put("/api/tokens/{token_id}", response_model=TokenResponse)
async def update_token(
    token_id: str,
    token_data: TokenUpdate,
    background_tasks: BackgroundTasks,
    current_user: dict = Depends(get_current_user)
):
    """Token bilgilerini güncelle"""
    update_data = token_data.dict(exclude_unset=True)
    if not update_data:
        raise HTTPException(status_code=400, detail="Güncellenecek veri yok!")

    result = await db.tokens.find_one_and_update(
        {"_id": ObjectId(token_id)},
        {"$set": update_data},
        return_document=True
    )

    if not result:
        raise HTTPException(status_code=404, detail="Token bulunamadı!")

    result["id"] = str(result["_id"])
    
    # WebSocket bildirimi gönder
    background_tasks.add_task(broadcast_update, {
        "type": "token_updated",
        "data": result
    })
    
    return TokenResponse(**result)

@app.post("/api/tokens/{token_id}/start")
async def start_token(
    token_id: str,
    background_tasks: BackgroundTasks,
    current_user: dict = Depends(get_current_user)
):
    """Token farming işlemini başlat"""
    token = await db.tokens.find_one({"_id": ObjectId(token_id)})
    if not token:
        raise HTTPException(status_code=404, detail="Token bulunamadı!")
    
    if token["status"] == "busy":
        raise HTTPException(status_code=400, detail="Token zaten çalışıyor!")
    
    if token["status"] == "banned":
        raise HTTPException(status_code=400, detail="Token banlı durumda!")
    
    # Token yöneticisi oluştur
    manager = TokenManager(token["token"], token["channel_id"])
    token_managers[token_id] = manager
    
    # Farming işlemini başlat
    background_tasks.add_task(manager.start_farming)
    
    # Token durumunu güncelle
    await db.tokens.update_one(
        {"_id": ObjectId(token_id)},
        {
            "$set": {
                "status": "busy",
                "last_update": datetime.utcnow(),
                "is_running": True
            }
        }
    )
    
    # WebSocket bildirimi gönder
    background_tasks.add_task(broadcast_update, {
        "type": "token_started",
        "data": {
            "token_id": token_id,
            "status": "busy",
            "last_update": datetime.utcnow().isoformat()
        }
    })
    
    return {"status": "started"}

@app.post("/api/tokens/{token_id}/stop")
async def stop_token(
    token_id: str,
    background_tasks: BackgroundTasks,
    current_user: dict = Depends(get_current_user)
):
    """Token farming işlemini durdur"""
    token = await db.tokens.find_one({"_id": ObjectId(token_id)})
    if not token:
        raise HTTPException(status_code=404, detail="Token bulunamadı!")
    
    if token_id in token_managers:
        manager = token_managers[token_id]
        manager.stop_farming()
        
        # Token durumunu güncelle
        stats = manager.stats
        await db.tokens.update_one(
            {"_id": ObjectId(token_id)},
            {
                "$set": {
                    "status": "available",
                    "owo_balance": stats["owo_balance"],
                    "total_messages": stats["total_messages"],
                    "last_update": datetime.utcnow(),
                    "is_running": False
                }
            }
        )
        
        del token_managers[token_id]
        
        # WebSocket bildirimi gönder
        background_tasks.add_task(broadcast_update, {
            "type": "token_stopped",
            "data": {
                "token_id": token_id,
                "status": "available",
                "stats": stats
            }
        })
    
    return {"status": "stopped"} 