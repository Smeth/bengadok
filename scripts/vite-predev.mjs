/**
 * Avant `npm run dev` : supprime public/hot obsolète (ex. [::1]:5173 sous Windows).
 */
import fs from 'node:fs'
import path from 'node:path'

const hotPath = path.join(process.cwd(), 'public', 'hot')

if (!fs.existsSync(hotPath)) {
    process.exit(0)
}

const url = fs.readFileSync(hotPath, 'utf8').trim()

if (url.includes('[::1]')) {
    fs.unlinkSync(hotPath)
    console.log('[vite-predev] public/hot supprimé (URL IPv6 [::1] — utilisez localhost).')
}
