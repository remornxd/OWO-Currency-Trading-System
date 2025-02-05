require('dotenv').config();
const { REST, Routes } = require('discord.js');

const commands = [
    {
        name: 'paketler',
        description: 'Mevcut OWO paketlerini görüntüle'
    },
    {
        name: 'teslim',
        description: 'OWO key\'ini kullan ve farming işlemini başlat',
        options: [
            {
                name: 'key',
                type: 3, // STRING
                description: 'Satın aldığınız key',
                required: true
            }
        ]
    }
];

const rest = new REST({ version: '10' }).setToken(process.env.DISCORD_TOKEN);

(async () => {
    try {
        console.log('Slash komutları kaydediliyor...');

        await rest.put(
            Routes.applicationCommands(process.env.CLIENT_ID),
            { body: commands }
        );

        console.log('Slash komutları başarıyla kaydedildi!');
    } catch (error) {
        console.error(error);
    }
})(); 