import express from 'express'

import { morganMiddleware } from '@/middlewares'
import { V1Router } from '@/api/v1/router'

const app = express()

// ========== Apply Middleware 👇 ==========
app.use(express.json())
app.use(morganMiddleware) // only runs in development mode

// ========== Apply Routes 👇 ==========
app.use('/api/v1', V1Router)

export { app }
