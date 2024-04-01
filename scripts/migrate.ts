import { drizzle } from 'drizzle-orm/postgres-js'
import { migrate } from 'drizzle-orm/postgres-js/migrator'
import postgres from 'postgres'
import * as dotenv from 'dotenv'

dotenv.config()

const PG_CONNECTION_STRING = process.env.PG_CONNECTION_STRING ?? ''

async function migrateDB() {
	if (!PG_CONNECTION_STRING) {
		console.error('=> 💥 PG_CONNECTION_STRING not set')
		process.exit(1)
	}

	try {
		const client = postgres(PG_CONNECTION_STRING)
		const db = drizzle(client)

		console.log('=> 🚀 Migrating the database...')
		console.log('=> Database URL: ', PG_CONNECTION_STRING)

		await migrate(db, { migrationsFolder: 'drizzle' })

		console.log('=> ✅ Database migrated successfully')
		process.exit(0)
	} catch (err: unknown) {
		console.error('=> 💥 An error occurred while migrating the database:', err)
		process.exit(1)
	}
}

migrateDB()
