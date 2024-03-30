import http from 'node:http'

import * as dotenv from 'dotenv'

import { app } from './app'
import { env } from './config/env'
import { logger } from './utils'

dotenv.config()

let server: http.Server
const { NODE_ENV, PORT } = env
const SIGNALS = ['SIGINT', 'SIGTERM']

async function runServer() {
	server = http.createServer(app)

	server.listen(PORT, () => {
		logger.info(`=> 🚀 Server is running in ${NODE_ENV.toUpperCase()} mode on port ${PORT}}`)
	})
}

function onCloseSignal() {
	logger.info('=> 🛑 STOPE SIGNAL RECEIVED: Server is shutting down...')
	server.close(() => {
		logger.info('=> 🛑 Server has been shut down')
	})
}

for (const signal of SIGNALS) {
	process.on(signal, onCloseSignal)
}

runServer()
