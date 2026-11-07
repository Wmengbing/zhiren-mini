// Production uses the company domain; config.local.js can override either environment.
let local = {}
try { local = require('./config.local') } catch (_) {}
const version = wx.getAccountInfoSync().miniProgram.envVersion
const production = version === 'release' || version === 'trial'
const base = production ? (local.productionApiBase || 'https://zhirencasting.cc') : (local.developmentApiBase || 'http://127.0.0.1:8000')
const normalized = String(base || '').replace(/\/+$/, '')
const valid = !production || /^https:\/\/(?!localhost(?:[:/]|$)|127\.|0\.0\.0\.0)[^/\s]+$/i.test(normalized)
module.exports = { apiBase: valid ? normalized : '', production }
