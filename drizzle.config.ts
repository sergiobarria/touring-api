import * as dotenv from 'dotenv'
import { defineConfig } from 'drizzle-kit'

dotenv.config()

export default defineConfig({
	schema: './src/lib/schemas.ts',
	out: './drizzle',
	driver: 'pg',
	dbCredentials: {
		connectionString: process.env.PG_CONNECTION_STRING ?? '',
	},
	tablesFilter: ['touric_dev_*'],
})
