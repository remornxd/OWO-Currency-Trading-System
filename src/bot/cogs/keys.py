import discord
from discord.ext import commands
from discord import app_commands
from ..database import get_database

class Keys(commands.Cog):
    def __init__(self, bot):
        self.bot = bot
        self.db = get_database()

    @app_commands.command(name="teslim", description="OWO key'ini kullan ve farming işlemini başlat")
    async def redeem_key(self, interaction: discord.Interaction, key: str):
        # Key kontrolü
        key_data = await self.db.keys.find_one({"key": key, "isUsed": False})
        if not key_data:
            return await interaction.response.send_message(
                "❌ Geçersiz veya kullanılmış key!",
                ephemeral=True
            )

        # Key'i kullanıldı olarak işaretle
        await self.db.keys.update_one(
            {"_id": key_data["_id"]},
            {
                "$set": {
                    "isUsed": True,
                    "usedBy": str(interaction.user.id),
                    "usedAt": discord.utils.utcnow()
                }
            }
        )

        # Farming işlemini başlat
        await self.start_farming(interaction, key_data["packageType"])

    async def start_farming(self, interaction: discord.Interaction, package_type: int):
        # Kullanılabilir tokenleri bul
        tokens = await self.db.tokens.find({"status": "available"}).limit(10).to_list(10)
        
        if not tokens:
            return await interaction.followup.send(
                "❌ Şu anda uygun token bulunmuyor. Lütfen daha sonra tekrar deneyin.",
                ephemeral=True
            )

        packages = {
            1: "2M OWO",
            2: "5M OWO",
            3: "10M OWO"
        }

        # Tokenleri meşgul olarak işaretle
        for token in tokens:
            await self.db.tokens.update_one(
                {"_id": token["_id"]},
                {
                    "$set": {
                        "status": "busy",
                        "currentUser": str(interaction.user.id),
                        "packageType": package_type,
                        "startedAt": discord.utils.utcnow()
                    }
                }
            )

        await interaction.followup.send(
            f"✅ Farming başlatıldı! Hedef: {packages[package_type]}",
            ephemeral=True
        )

async def setup(bot):
    await bot.add_cog(Keys(bot)) 