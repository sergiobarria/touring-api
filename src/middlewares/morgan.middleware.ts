import morgan, { type StreamOptions } from 'morgan'

import { env } from '@/config/env'
import { logger } from '@/utils/logger'

const stream: StreamOptions = {
	write: message => logger.http(message),
}

const skip = () => {
	return env.NODE_ENV !== 'development'
}

export const morganMiddleware = morgan(
	':remote-addr :method :url :status :res[content-length] :response-time ms',
	{ stream, skip }
)
