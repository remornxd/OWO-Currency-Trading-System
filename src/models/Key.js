const mongoose = require('mongoose');

const keySchema = new mongoose.Schema({
    key: {
        type: String,
        required: true,
        unique: true
    },
    packageType: {
        type: Number,
        required: true // 1 for 2M OWO, 2 for 5M OWO, etc.
    },
    isUsed: {
        type: Boolean,
        default: false
    },
    usedBy: {
        type: String,
        default: null
    },
    createdAt: {
        type: Date,
        default: Date.now
    }
});

module.exports = mongoose.model('Key', keySchema); 