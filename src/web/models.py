from pydantic import BaseModel, Field, EmailStr
from typing import Optional
from datetime import datetime

# Token Models
class TokenBase(BaseModel):
    token: str = Field(..., description="Discord token")
    channel_id: str = Field(..., description="Discord kanal ID'si")

class TokenCreate(TokenBase):
    pass

class TokenUpdate(BaseModel):
    status: Optional[str] = Field(None, description="Token durumu")
    owo_balance: Optional[int] = Field(None, description="OWO bakiyesi")
    channel_id: Optional[str] = Field(None, description="Discord kanal ID'si")

class TokenResponse(TokenBase):
    id: str
    status: str
    owo_balance: int
    total_messages: int
    last_update: datetime
    is_running: bool
    captcha_detected: bool

    class Config:
        orm_mode = True

class TokenStats(BaseModel):
    total_tokens: int
    active_tokens: int
    busy_tokens: int
    banned_tokens: int
    total_owo: int
    total_messages: int

# User Models
class UserBase(BaseModel):
    username: str = Field(..., min_length=3, max_length=50)
    email: Optional[EmailStr] = None

class UserCreate(UserBase):
    password: str = Field(..., min_length=6)
    key: str = Field(..., description="Kayıt için gerekli key")

class UserLogin(BaseModel):
    username: str
    password: str

class UserResponse(UserBase):
    id: str
    role: str
    package_type: int
    created_at: datetime

    class Config:
        orm_mode = True

# Package Models
class Package(BaseModel):
    id: int
    name: str
    amount: str
    price: str
    color: int

# Chart Models
class ChartData(BaseModel):
    labels: list[str]
    datasets: list[dict] 