from discord.ext.self import commands
from discord.ext.self import tasks
import discord.ext.self as discord
from .database import get_database
import random
import string
import asyncio
from datetime import datetime, timedelta
import os
from dotenv import load_dotenv

load_dotenv()

class OwoBot(commands.Bot):
    def __init__(self, command_prefix, description):
        intents = discord.Intents.default()
        intents.message_content = True
        intents.guilds = True
        
        super().__init__(
            command_prefix=command_prefix,
            description=description,
            intents=intents,
            self_bot=True
        )
        
        self.db = get_database()
        self.notification_channel_id = None  # Bildirim kanalı ID'si
        self.packages = {
            1: {"name": "Başlangıç Paketi", "amount": "2M OWO", "price": "50 TL", "color": 0x3498db},
            2: {"name": "Orta Paket", "amount": "5M OWO", "price": "100 TL", "color": 0x2ecc71},
            3: {"name": "Pro Paket", "amount": "10M OWO", "price": "180 TL", "color": 0xe74c3c}
        }
        self.status_update.start()

    def cog_unload(self):
        self.status_update.cancel()

    @tasks.loop(minutes=5)
    async def status_update(self):
        try:
            activities = [
                discord.Activity(type=discord.ActivityType.watching, name="OWO Farming"),
                discord.Activity(type=discord.ActivityType.playing, name="with OWO"),
                discord.Activity(type=discord.ActivityType.listening, name="OWO Commands")
            ]
            activity = random.choice(activities)
            await self.change_presence(activity=activity)
        except Exception as e:
            print(f"Status update error: {e}")

    @status_update.before_loop
    async def before_status_update(self):
        await self.wait_until_ready()

    async def setup_hook(self):
        for filename in os.listdir('./src/bot/cogs'):
            if filename.endswith('.py'):
                try:
                    await self.load_extension(f'bot.cogs.{filename[:-3]}')
                    print(f'Loaded extension: {filename}')
                except Exception as e:
                    print(f'Failed to load extension {filename}: {e}')

    def generate_key(self, length=16):
        chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
        return ''.join(random.choice(chars) for _ in range(length))

    @commands.command(name="setnotifychannel", help="Bildirim kanalını ayarla")
    @commands.has_permissions(administrator=True)
    async def set_notify_channel(self, ctx):
        self.notification_channel_id = ctx.channel.id
        embed = discord.Embed(
            title="✅ Bildirim Kanalı Ayarlandı",
            description=f"Bundan sonra tüm sistem bildirimleri bu kanala gönderilecek.",
            color=0x2ecc71
        )
        await ctx.send(embed=embed)

    @commands.command(name="paketler", help="Mevcut OWO paketlerini görüntüle")
    async def show_packages(self, ctx):
        embed = discord.Embed(
            title="🎮 OWO Paketleri",
            description="Aşağıdaki paketlerden birini seçebilirsiniz:",
            color=0x2ecc71
        )

        for id, pack in self.packages.items():
            embed.add_field(
                name=f"{pack['name']} 🏷️",
                value=f"```Miktar: {pack['amount']}\nFiyat: {pack['price']}```",
                inline=False
            )

        embed.set_footer(text="Satın almak için yetkililere ulaşın")
        await ctx.send(embed=embed)

    @commands.command(name="teslim", help="OWO key'ini kullan ve farming işlemini başlat")
    async def redeem_key(self, ctx, key: str):
        async with ctx.typing():
            # Key kontrolü
            key_data = await self.db.keys.find_one({"key": key, "isUsed": False})
            if not key_data:
                embed = discord.Embed(
                    title="❌ Hata",
                    description="Geçersiz veya kullanılmış key!",
                    color=0xe74c3c
                )
                return await ctx.send(embed=embed)

            # Key'i kullanıldı olarak işaretle
            await self.db.keys.update_one(
                {"_id": key_data["_id"]},
                {
                    "$set": {
                        "isUsed": True,
                        "usedBy": str(ctx.author.id),
                        "usedAt": datetime.utcnow()
                    }
                }
            )

            # Kullanılabilir tokenleri bul
            tokens = await self.db.tokens.find({"status": "available"}).limit(10).to_list(10)
            
            if not tokens:
                embed = discord.Embed(
                    title="❌ Hata",
                    description="Şu anda uygun token bulunmuyor. Lütfen daha sonra tekrar deneyin.",
                    color=0xe74c3c
                )
                return await ctx.send(embed=embed)

            # Tokenlerin OWO bakiyelerini kontrol et
            total_owo = 0
            for token in tokens:
                manager = TokenManager(token["token"], token["channel_id"])
                await manager.initialize()
                balance = await manager.get_balance()
                total_owo += balance
                await manager.cleanup()

            # Bakiye transferi için embed hazırla
            embed = discord.Embed(
                title="💰 OWO Transfer İşlemi",
                description=f"Toplam {total_owo:,} OWO transfer edilecek.\nOnaylamak için ✅ Confirm, iptal etmek için ❌ Cancel butonuna tıklayın.",
                color=0x2ecc71
            )
            embed.add_field(
                name="Gönderen",
                value=f"`{tokens[0]['token'][:20]}...`",
                inline=False
            )
            embed.add_field(
                name="Alan",
                value=f"{ctx.author.mention}",
                inline=False
            )
            embed.add_field(
                name="Miktar",
                value=f"`{total_owo:,} OWO`",
                inline=False
            )

            # Onay butonları
            confirm_view = discord.ui.View()
            confirm_button = discord.ui.Button(
                style=discord.ButtonStyle.success,
                label="Confirm",
                emoji="✅",
                custom_id="confirm_transfer"
            )
            cancel_button = discord.ui.Button(
                style=discord.ButtonStyle.danger,
                label="Cancel",
                emoji="❌",
                custom_id="cancel_transfer"
            )
            confirm_view.add_item(confirm_button)
            confirm_view.add_item(cancel_button)

            # Onay mesajını gönder
            confirmation_msg = await ctx.send(embed=embed, view=confirm_view)

            try:
                # Kullanıcının buton tıklamasını bekle
                interaction = await self.wait_for(
                    "button_click",
                    check=lambda i: i.message.id == confirmation_msg.id and i.user.id == ctx.author.id,
                    timeout=60.0
                )

                if interaction.custom_id == "confirm_transfer":
                    # Transfer işlemini başlat
                    main_token = tokens[0]
                    manager = TokenManager(main_token["token"], main_token["channel_id"])
                    await manager.initialize()
                    
                    # Transfer komutu
                    await manager.send_message(f"owo send {ctx.author.id} {total_owo}")
                    
                    await manager.cleanup()

                    success_embed = discord.Embed(
                        title="✅ Transfer Başarılı",
                        description=f"{total_owo:,} OWO başarıyla transfer edildi!",
                        color=0x2ecc71
                    )
                    await confirmation_msg.edit(embed=success_embed, view=None)

                else:
                    # İptal edildi
                    cancel_embed = discord.Embed(
                        title="❌ Transfer İptal Edildi",
                        description="İşlem kullanıcı tarafından iptal edildi.",
                        color=0xe74c3c
                    )
                    await confirmation_msg.edit(embed=cancel_embed, view=None)

            except asyncio.TimeoutError:
                # Zaman aşımı
                timeout_embed = discord.Embed(
                    title="⏰ Zaman Aşımı",
                    description="İşlem zaman aşımına uğradı.",
                    color=0xe74c3c
                )
                await confirmation_msg.edit(embed=timeout_embed, view=None)

    @commands.command(name="keyolustur", help="Yeni bir key oluştur (Sadece yöneticiler)")
    @commands.has_permissions(administrator=True)
    async def create_key(self, ctx, package_type: int):
        if package_type not in self.packages:
            embed = discord.Embed(
                title="❌ Hata",
                description="Geçersiz paket tipi!",
                color=0xe74c3c
            )
            return await ctx.send(embed=embed)

        key = self.generate_key()
        await self.db.keys.insert_one({
            "key": key,
            "packageType": package_type,
            "isUsed": False,
            "createdBy": str(ctx.author.id),
            "createdAt": datetime.utcnow()
        })

        embed = discord.Embed(
            title="🔑 Key Oluşturuldu",
            description=f"**Paket:** {self.packages[package_type]['name']}\n**Key:** `{key}`",
            color=self.packages[package_type]['color']
        )
        embed.set_footer(text=f"Oluşturan: {ctx.author.name}")
        
        try:
            await ctx.author.send(embed=embed)
            await ctx.send("✅ Key DM olarak gönderildi!")
        except:
            await ctx.send(embed=embed)

    @commands.command(name="yardım", help="Komut listesini gösterir")
    async def help_command(self, ctx):
        embed = discord.Embed(
            title="📚 Komut Listesi",
            description="Kullanılabilir komutlar:",
            color=0x3498db
        )

        embed.add_field(
            name=".paketler",
            value="Mevcut OWO paketlerini görüntüler",
            inline=False
        )
        embed.add_field(
            name=".teslim <key>",
            value="Satın aldığınız key ile farming işlemini başlatır",
            inline=False
        )
        if ctx.author.guild_permissions.administrator:
            embed.add_field(
                name=".keyolustur <paket_tipi>",
                value="Yeni bir key oluşturur (Sadece yöneticiler)",
                inline=False
            )
            embed.add_field(
                name=".setnotifychannel",
                value="Bildirim kanalını ayarlar (Sadece yöneticiler)",
                inline=False
            )

        embed.set_footer(text=f"Requested by {ctx.author.name}")
        await ctx.send(embed=embed)

    async def start_farming(self, ctx, package_type: int):
        # Kullanılabilir tokenleri bul
        tokens = await self.db.tokens.find({"status": "available"}).limit(10).to_list(10)
        
        if not tokens:
            embed = discord.Embed(
                title="❌ Hata",
                description="Şu anda uygun token bulunmuyor. Lütfen daha sonra tekrar deneyin.",
                color=0xe74c3c
            )
            return await ctx.send(embed=embed)

        # Tokenleri meşgul olarak işaretle
        for token in tokens:
            await self.db.tokens.update_one(
                {"_id": token["_id"]},
                {
                    "$set": {
                        "status": "busy",
                        "currentUser": str(ctx.author.id),
                        "packageType": package_type,
                        "startedAt": datetime.utcnow()
                    }
                }
            )

        package = self.packages[package_type]
        embed = discord.Embed(
            title="✅ Farming Başlatıldı",
            description=f"**Hedef:** {package['amount']}\n**Kullanılan Token:** {len(tokens)}",
            color=package['color']
        )
        embed.add_field(
            name="Tahmini Süre",
            value="```24-48 saat```",
            inline=False
        )
        embed.add_field(
            name="Durum Bildirimi",
            value="Her 5 dakikada bir durum güncellemesi alacaksınız.",
            inline=False
        )
        embed.set_footer(text="İşlem otomatik olarak gerçekleştirilecek")
        
        await ctx.send(embed=embed)

        # Bildirim kanalına da bilgi gönder
        if self.notification_channel_id:
            try:
                channel = self.get_channel(int(self.notification_channel_id))
                if channel:
                    notify_embed = discord.Embed(
                        title="🚀 Yeni Farming Başlatıldı",
                        description=f"**Kullanıcı:** {ctx.author.mention}\n**Paket:** {package['name']}\n**Token Sayısı:** {len(tokens)}",
                        color=package['color'],
                        timestamp=datetime.utcnow()
                    )
                    await channel.send(embed=notify_embed)
            except Exception as e:
                print(f"Notification error: {e}") 