import * as dotenv from 'dotenv'
import { cleanEnv, host, num, port, str } from 'envalid'

dotenv.config()

export const env = cleanEnv(process.env, {
	NODE_ENV: str({ choices: ['development', 'production'] }),
	PORT: port(),
	APP_VERSION: str(),
	PG_CONNECTION_STRING: str(),
	PG_HOST: str(),
	PG_DATABASE: str(),
	PG_USER: str(),
	PG_PASSWORD: str(),
	PG_PORT: num(),
})
