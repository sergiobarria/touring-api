import { z } from 'zod'

const payload = {
	body: z.object({
		name: z
			.string({
				required_error: 'Tour name is required.',
			})
			.min(10, 'Tour name must be at least 10 characters.')
			.max(40, 'Tour name must be at most 40 characters.'),
		duration: z.coerce.number({
			required_error: 'Tour duration is required.',
		}),
		maxGroupSize: z.coerce.number({
			required_error: 'Tour max group size is required.',
		}),
		difficulty: z.enum(['easy', 'medium', 'difficult'], {
			required_error: 'Tour difficulty is required.',
		}),
		ratingsAverage: z.coerce.number({
			required_error: 'Tour average rating is required.',
		}),
		ratingsQuantity: z.coerce.number({
			required_error: 'Tour rating quantity is required.',
		}),
		price: z.coerce.number({
			required_error: 'Tour price is required.',
		}),
		summary: z.string({
			required_error: 'Tour summary is required.',
		}),
		description: z.string({
			required_error: 'Tour description is required.',
		}),
	}),
}

const params = {
	params: z.object({
		id: z.string({
			required_error: 'Tour ID is required.',
		}),
	}),
}

export const createTourSchema = z.object({
	...payload,
})

export const getTourParamsSchema = z.object({
	...params,
})

export type CreateTourBody = z.infer<typeof createTourSchema>['body']
export type GetTourParams = z.infer<typeof getTourParamsSchema>['params']
