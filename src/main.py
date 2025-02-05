import asyncio
import uvicorn
from bot.bot import OwoBot
from web.main import app
import multiprocessing
import os
from dotenv import load_dotenv

load_dotenv()

def run_web():
    """Web sunucusunu başlat"""
    uvicorn.run(app, host="0.0.0.0", port=8000)

async def run_bot():
    """Discord botunu başlat"""
    bot = OwoBot(command_prefix=".", description="OWO Para Kasma Sistemi")
    await bot.start(os.getenv("DISCORD_TOKEN"))

def main():
    # Web sunucusunu ayrı bir process'te başlat
    web_process = multiprocessing.Process(target=run_web)
    web_process.start()

    # Discord botunu ana process'te başlat
    asyncio.run(run_bot())

if __name__ == "__main__":
    main() 