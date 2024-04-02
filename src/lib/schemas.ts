import { serial, varchar, integer, decimal, text, pgTableCreator, timestamp, pgEnum } from 'drizzle-orm/pg-core';
import { relations } from 'drizzle-orm';

// The following allows to prefix all the tables in the schema with 'touric_dev_'
// This is useful to reuse the same database for different projects, specially during development
// For this to work, the `tablesFilter` in drizzle.config.ts must be set to ['touric_dev_*']
// read more: https://orm.drizzle.team/kit-docs/config-reference#tablefilters
const pgTable = pgTableCreator(name => `touric_dev_${name}`);

export const difficultyEnum = pgEnum('difficulty', ['easy', 'medium', 'difficult']);

export const tours = pgTable('tours', {
	id: serial('id').primaryKey(),

	// columns
	name: varchar('name', { length: 256 }).unique(),
	slug: varchar('slug', { length: 256 }).unique(),
	duration: integer('duration').notNull(),
	maxGroupSize: integer('max_group_size').notNull(),
	difficulty: difficultyEnum('difficulty').notNull(),
	ratingAvg: decimal('rating_avg', { precision: 2, scale: 1 }).notNull().default('0.0'),
	ratingQty: integer('rating_qty').notNull().default(0),
	price: decimal('price', { precision: 10, scale: 2 }).notNull(),
	priceDiscount: decimal('price_discount', { precision: 10, scale: 2 }),
	summary: text('summary').notNull(),
	description: text('description').notNull(),
	coverImage: text('cover_image'),
	startDates: text('start_dates').array(),

	// timestamps
	createdAt: timestamp('created_at', { withTimezone: true }).defaultNow(),
	updatedAt: timestamp('updated_at', { withTimezone: true }).defaultNow(),
});

export const toursRelations = relations(tours, ({ many }) => ({
	images: many(tourImages),
}));

export const tourImages = pgTable('images', {
	id: serial('id').primaryKey(),

	// columns
	url: text('url').notNull(),
	tourId: integer('tour_id').notNull(),

	// timestamps
	createdAt: timestamp('created_at', { withTimezone: true }).defaultNow(),
	updatedAt: timestamp('updated_at', { withTimezone: true }).defaultNow(),
});

export const imagesRelations = relations(tourImages, ({ one }) => ({
	tour: one(tours, {
		fields: [tourImages.tourId],
		references: [tours.id],
	}),
}));
