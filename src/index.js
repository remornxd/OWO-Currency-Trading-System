require('dotenv').config();
const { Client, GatewayIntentBits, ActionRowBuilder, ButtonBuilder, ButtonStyle, EmbedBuilder } = require('discord.js');
const mongoose = require('mongoose');
const Key = require('./models/Key');
const Token = require('./models/Token');

const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent
    ]
});

mongoose.connect(process.env.MONGODB_URI).then(() => {
    console.log('Connected to MongoDB');
}).catch(err => {
    console.error('MongoDB connection error:', err);
});

const packages = {
    1: { name: "Başlangıç Paketi", amount: "2M OWO" },
    2: { name: "Orta Paket", amount: "5M OWO" },
    3: { name: "Pro Paket", amount: "10M OWO" }
};

client.once('ready', () => {
    console.log(`Bot ${client.user.tag} olarak giriş yaptı!`);
});

client.on('interactionCreate', async interaction => {
    if (!interaction.isCommand() && !interaction.isButton()) return;

    if (interaction.isCommand()) {
        if (interaction.commandName === 'paketler') {
            const embed = new EmbedBuilder()
                .setTitle('🎮 OWO Paketleri')
                .setDescription('Aşağıdaki paketlerden birini seçin:')
                .setColor('#FF5733');

            Object.entries(packages).forEach(([id, pack]) => {
                embed.addFields({ name: pack.name, value: `Miktar: ${pack.amount}` });
            });

            const row = new ActionRowBuilder()
                .addComponents(
                    new ButtonBuilder()
                        .setCustomId('package_1')
                        .setLabel('Başlangıç Paketi')
                        .setStyle(ButtonStyle.Primary),
                    new ButtonBuilder()
                        .setCustomId('package_2')
                        .setLabel('Orta Paket')
                        .setStyle(ButtonStyle.Success),
                    new ButtonBuilder()
                        .setCustomId('package_3')
                        .setLabel('Pro Paket')
                        .setStyle(ButtonStyle.Danger)
                );

            await interaction.reply({ embeds: [embed], components: [row] });
        }

        if (interaction.commandName === 'teslim') {
            const key = interaction.options.getString('key');
            const keyData = await Key.findOne({ key: key, isUsed: false });

            if (!keyData) {
                return interaction.reply({ content: '❌ Geçersiz veya kullanılmış key!', ephemeral: true });
            }

            // Key kullanım işaretleme
            keyData.isUsed = true;
            keyData.usedBy = interaction.user.id;
            await keyData.save();

            // Token farm işlemini başlat
            startFarming(interaction, keyData.packageType);
        }
    }

    if (interaction.isButton()) {
        if (interaction.customId.startsWith('package_')) {
            const packageId = interaction.customId.split('_')[1];
            const embed = new EmbedBuilder()
                .setTitle(`📦 ${packages[packageId].name}`)
                .setDescription(`Paket detayları:\nMiktar: ${packages[packageId].amount}\n\nSatın alma işlemi için yetkili ile iletişime geçin.`)
                .setColor('#33FF57');

            await interaction.reply({ embeds: [embed], ephemeral: true });
        }
    }
});

async function startFarming(interaction, packageType) {
    // Token seçimi ve farming başlatma
    const availableTokens = await Token.find({ status: 'available' }).limit(10);
    
    if (availableTokens.length === 0) {
        return interaction.followUp({ content: '❌ Şu anda uygun token bulunmuyor. Lütfen daha sonra tekrar deneyin.', ephemeral: true });
    }

    const amount = packages[packageType].amount;
    await interaction.followUp({ content: `✅ Farming başlatıldı! ${amount} OWO kazanılacak.`, ephemeral: true });

    // Token farming simülasyonu
    availableTokens.forEach(async (token) => {
        token.status = 'busy';
        await token.save();
        
        // Gerçek implementasyonda burada token ile mesaj gönderme ve para kasma işlemleri yapılacak
    });
}

client.login(process.env.DISCORD_TOKEN); 