import winston from 'winston'

import { env } from '@/config/env'

const levels = {
	error: 0,
	warn: 1,
	info: 2,
	http: 3,
	debug: 4,
}

const level = () => {
	return env.NODE_ENV === 'development' ? 'debug' : 'info'
}

const colors = {
	error: 'red',
	warn: 'yellow',
	info: 'green',
	http: 'magenta',
	debug: 'blue',
}

winston.addColors(colors)

const format = winston.format.combine(
	winston.format.timestamp({ format: 'YYYY-MM-DD HH:mm:ss A' }),
	winston.format(info => {
		info.level = info.level.toUpperCase()
		return info
	})(),
	winston.format.colorize({ all: true }),
	winston.format.printf(info => `${info.timestamp} [${info.level}]: ${info.message}`)
)

const transports = [new winston.transports.Console()]

export const logger = winston.createLogger({
	level: level(),
	levels,
	format,
	transports,
})
