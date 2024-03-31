import express from 'express'
import { StatusCodes } from 'http-status-codes'

import { toursRouter } from './tours'
import { env } from '@/config/env'

const router = express.Router()

router.get('/health', (req, res) => {
	const systemResources = {
		cpuUsage: process.cpuUsage(),
		memoryUsage: {
			total: process.memoryUsage().heapTotal,
			used: process.memoryUsage().heapUsed,
			free: process.memoryUsage().heapTotal - process.memoryUsage().heapUsed,
		},
	}

	res.status(StatusCodes.OK).json({
		status: 'ok',
		version: env.APP_VERSION,
		description: 'Touric REST API is running successfully.',
		serverTime: new Date().toISOString(),
		systemResources,
	})
})

// ========== Apply Routes 👇 =========
router.use('/tours', toursRouter)

export { router as V1Router }
