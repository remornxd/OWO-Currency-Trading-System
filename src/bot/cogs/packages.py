import discord
from discord.ext import commands
from discord import app_commands
from ..database import get_database

class Packages(commands.Cog):
    def __init__(self, bot):
        self.bot = bot
        self.db = get_database()
        self.packages = {
            1: {"name": "Başlangıç Paketi", "amount": "2M OWO", "price": "50 TL"},
            2: {"name": "Orta Paket", "amount": "5M OWO", "price": "100 TL"},
            3: {"name": "Pro Paket", "amount": "10M OWO", "price": "180 TL"}
        }

    @app_commands.command(name="paketler", description="Mevcut OWO paketlerini görüntüle")
    async def show_packages(self, interaction: discord.Interaction):
        embed = discord.Embed(
            title="🎮 OWO Paketleri",
            description="Aşağıdaki paketlerden birini seçin:",
            color=discord.Color.blue()
        )

        for id, pack in self.packages.items():
            embed.add_field(
                name=pack["name"],
                value=f"Miktar: {pack['amount']}\nFiyat: {pack['price']}",
                inline=False
            )

        view = discord.ui.View()
        for id, pack in self.packages.items():
            button = discord.ui.Button(
                style=discord.ButtonStyle.primary,
                label=pack["name"],
                custom_id=f"package_{id}"
            )
            view.add_item(button)

        await interaction.response.send_message(embed=embed, view=view)

async def setup(bot):
    await bot.add_cog(Packages(bot)) 