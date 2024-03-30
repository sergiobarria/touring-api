import express from 'express'

import { morganMiddleware } from '@/middlewares'

const app = express()

// ========== Apply Middleware 👇 ==========
app.use(express.json())
app.use(morganMiddleware)

app.get('/hello', (req, res) => {
	res.send('Hello World')
})

export { app }
