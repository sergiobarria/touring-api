import * as dotenv from 'dotenv'
import { cleanEnv, host, num, port, str } from 'envalid'

dotenv.config()

export const env = cleanEnv(process.env, {
	NODE_ENV: str({ choices: ['development', 'production'] }),
	PORT: port(),
})
