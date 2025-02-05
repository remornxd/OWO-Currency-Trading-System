from fastapi import Depends, HTTPException, Security
from fastapi.security import OAuth2PasswordBearer
from jose import JWTError, jwt
from datetime import datetime, timedelta
from typing import Optional
from ..database import get_database

# Güvenlik ayarları
SECRET_KEY = "your-secret-key-here"  # Production'da değiştirilmeli
ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 30

oauth2_scheme = OAuth2PasswordBearer(tokenUrl="api/auth/login")
db = get_database()

def create_access_token(data: dict, expires_delta: Optional[timedelta] = None):
    """JWT token oluştur"""
    to_encode = data.copy()
    if expires_delta:
        expire = datetime.utcnow() + expires_delta
    else:
        expire = datetime.utcnow() + timedelta(minutes=15)
    to_encode.update({"exp": expire})
    encoded_jwt = jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)
    return encoded_jwt

async def get_current_user(token: str = Depends(oauth2_scheme)):
    """Token'dan kullanıcı bilgilerini al"""
    credentials_exception = HTTPException(
        status_code=401,
        detail="Geçersiz kimlik bilgileri",
        headers={"WWW-Authenticate": "Bearer"},
    )
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        username: str = payload.get("sub")
        if username is None:
            raise credentials_exception
    except JWTError:
        raise credentials_exception
        
    user = await db.users.find_one({"username": username})
    if user is None:
        raise credentials_exception
        
    return user

async def get_current_admin(
    current_user: dict = Security(get_current_user, scopes=["admin"])
):
    """Admin yetkisi kontrolü"""
    if current_user.get("role") != "admin":
        raise HTTPException(
            status_code=403,
            detail="Bu işlem için admin yetkisi gerekiyor!"
        )
    return current_user 