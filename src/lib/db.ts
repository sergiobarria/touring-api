import { drizzle } from 'drizzle-orm/postgres-js';
import postgres from 'postgres';

import { env } from '@/config/env';
import * as schema from './schemas';

const { PG_HOST, PG_DATABASE, PG_PASSWORD, PG_USER, PG_PORT } = env;

const client = postgres({
	host: PG_HOST,
	database: PG_DATABASE,
	username: PG_USER,
	password: PG_PASSWORD,
	port: PG_PORT,
	ssl: 'require',
});

export const db = drizzle(client, { schema });
