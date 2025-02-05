import asyncio
import random
import aiohttp
from datetime import datetime, timedelta
import json
import re

class TokenManager:
    def __init__(self, token, channel_id):
        self.token = token
        self.channel_id = channel_id
        self.is_running = False
        self.total_messages = 0
        self.owo_balance = 0
        self.last_update = None
        self.last_hunt = None
        self.last_battle = None
        self.last_pray = None
        self.captcha_detected = False
        self.session = None
        self.headers = {
            "Authorization": token,
            "Content-Type": "application/json",
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
        }

    async def initialize(self):
        """Session'ı başlat"""
        self.session = aiohttp.ClientSession()
        self.is_running = True
        self.last_update = datetime.utcnow()

    async def cleanup(self):
        """Session'ı temizle"""
        if self.session:
            await self.session.close()
        self.is_running = False

    async def send_message(self, content):
        """Discord'a mesaj gönder"""
        if not self.session:
            await self.initialize()

        try:
            url = f"https://discord.com/api/v9/channels/{self.channel_id}/messages"
            payload = {"content": content}
            
            async with self.session.post(url, headers=self.headers, json=payload) as response:
                if response.status == 200:
                    self.total_messages += 1
                    return await response.json()
                elif response.status == 403:
                    self.captcha_detected = True
                    return None
        except Exception as e:
            print(f"Message error: {e}")
            return None

    async def get_balance(self):
        """OWO bakiyesini kontrol et"""
        try:
            response = await self.send_message("owo balance")
            if response:
                content = response.get("content", "")
                if match := re.search(r"(?:cowoncy|owo): \*\*([0-9,]+)\*\*", content, re.IGNORECASE):
                    self.owo_balance = int(match.group(1).replace(",", ""))
                    return self.owo_balance
        except Exception as e:
            print(f"Balance check error: {e}")
        return 0

    async def hunt(self):
        """Hunt komutu"""
        if not self.last_hunt or (datetime.utcnow() - self.last_hunt) > timedelta(seconds=15):
            await self.send_message("owo hunt")
            self.last_hunt = datetime.utcnow()

    async def battle(self):
        """Battle komutu"""
        if not self.last_battle or (datetime.utcnow() - self.last_battle) > timedelta(seconds=15):
            await self.send_message("owo battle")
            self.last_battle = datetime.utcnow()

    async def pray(self):
        """Pray komutu"""
        if not self.last_pray or (datetime.utcnow() - self.last_pray) > timedelta(minutes=5):
            await self.send_message("owo pray")
            self.last_pray = datetime.utcnow()

    async def start_farming(self):
        """Farming işlemini başlat"""
        if self.is_running:
            return

        await self.initialize()
        
        while self.is_running and not self.captcha_detected:
            try:
                # Ana komutları çalıştır
                await self.hunt()
                await asyncio.sleep(random.uniform(1, 2))
                
                await self.battle()
                await asyncio.sleep(random.uniform(1, 2))
                
                if random.random() < 0.2:  # %20 şansla pray at
                    await self.pray()
                
                # Her 5 dakikada bir bakiye kontrolü
                if not self.last_update or (datetime.utcnow() - self.last_update) > timedelta(minutes=5):
                    await self.get_balance()
                    self.last_update = datetime.utcnow()
                
                # Random bekleme süresi (12-15 saniye)
                await asyncio.sleep(random.uniform(12, 15))
                
            except Exception as e:
                print(f"Farming error: {e}")
                await asyncio.sleep(5)

        await self.cleanup()

    def stop_farming(self):
        """Farming işlemini durdur"""
        self.is_running = False

    @property
    def stats(self):
        """Token istatistiklerini döndür"""
        return {
            "total_messages": self.total_messages,
            "owo_balance": self.owo_balance,
            "last_update": self.last_update,
            "is_running": self.is_running,
            "captcha_detected": self.captcha_detected
        }

    @property
    def status(self):
        """Token durumunu döndür"""
        if self.captcha_detected:
            return "banned"
        elif self.is_running:
            return "busy"
        else:
            return "available" 