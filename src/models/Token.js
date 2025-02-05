const mongoose = require('mongoose');

const tokenSchema = new mongoose.Schema({
    token: {
        type: String,
        required: true,
        unique: true
    },
    isActive: {
        type: Boolean,
        default: true
    },
    lastUsed: {
        type: Date,
        default: Date.now
    },
    messageCount: {
        type: Number,
        default: 0
    },
    status: {
        type: String,
        enum: ['available', 'busy', 'banned', 'ratelimited'],
        default: 'available'
    }
});

module.exports = mongoose.model('Token', tokenSchema); 