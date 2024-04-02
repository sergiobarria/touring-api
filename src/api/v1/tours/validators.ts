import { z } from 'zod';
import { createInsertSchema, createSelectSchema } from 'drizzle-zod';

import { tours } from '@/lib/schemas';

const params = {
	params: z.object({
		id: z.string({
			required_error: 'Tour ID is required.',
		}),
	}),
};

export const tourSchema = createInsertSchema(tours, {
	startDates: z.array(z.string()),
});

export const selectTourSchema = createSelectSchema(tours, {
	id: z.number(),
	difficulty: z.enum(['easy', 'medium', 'difficult']),
});

export const insertTourSchema = z.object({
	body: tourSchema.extend({
		images: z.array(z.string()).optional(),
	}),
});

export const getTourParamsSchema = z.object({
	...params,
});

export const updateTourSchema = z.object({
	...params,
	body: tourSchema.partial(),
});

export type Tour = z.infer<typeof selectTourSchema>;
export type CreateTourBody = z.infer<typeof insertTourSchema>['body'];
export type GetTourParams = z.infer<typeof getTourParamsSchema>['params'];
