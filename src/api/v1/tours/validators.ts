import { z } from 'zod';
import { createInsertSchema } from 'drizzle-zod';

import { tours } from '@/lib/schemas';

const params = {
	params: z.object({
		id: z.string({
			required_error: 'Tour ID is required.',
		}),
	}),
};

export const tourSchema = createInsertSchema(tours, {
	price: z.coerce.number({ required_error: 'Price is required.' }),
	ratingAvg: z.coerce.number({ required_error: 'Rating average is required.' }),
	difficulty: z.enum(['easy', 'medium', 'difficult'], {
		required_error: 'Difficulty should be one of: easy, medium, difficult.',
	}),
});

export const insertTourSchema = z.object({
	body: tourSchema,
});

export const getTourParamsSchema = z.object({
	...params,
});

export const updateTourSchema = z.object({
	...params,
	body: tourSchema.partial(),
});

export type CreateTourBody = z.infer<typeof insertTourSchema>['body'];
export type GetTourParams = z.infer<typeof getTourParamsSchema>['params'];
