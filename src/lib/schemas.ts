import { serial, varchar, integer, decimal, text, pgTableCreator, timestamp } from 'drizzle-orm/pg-core';
import { sql } from 'drizzle-orm';

// The following allows to prefix all the tables in the schema with 'touric_dev_'
// This is useful to reuse the same database for different projects, specially during development
// For this to work, the `tablesFilter` in drizzle.config.ts must be set to ['touric_dev_*']
// read more: https://orm.drizzle.team/kit-docs/config-reference#tablefilters
const pgTable = pgTableCreator(name => `touric_dev_${name}`);

export const tours = pgTable('tours', {
	id: serial('id').primaryKey(),

	// columns
	name: varchar('name', { length: 256 }).unique(),
	ratingAvg: decimal('rating_avg', { precision: 2, scale: 1 }).notNull().$type<number>().default(0),
	duration: integer('duration').notNull(),
	maxGroupSize: integer('max_group_size').notNull(),
	difficulty: varchar('difficulty', { length: 40 }).notNull(),
	ratingQty: integer('rating_qty').notNull().default(0),
	price: decimal('price', { precision: 10, scale: 2 }).notNull().$type<number>(), // $type<number>() is used to infer the type of the column in TypeScript
	summary: text('summary').notNull(),
	description: text('description').notNull(),

	// timestamps
	createdAt: timestamp('created_at', { withTimezone: true }).default(sql`CURRENT_TIMESTAMP AT TIME ZONE 'UTC'`),
	updatedAt: timestamp('updated_at', { withTimezone: true }).default(sql`CURRENT_TIMESTAMP AT TIME ZONE 'UTC'`),
});
