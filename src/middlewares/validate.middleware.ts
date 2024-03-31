import type { Request, Response, NextFunction } from 'express'
import { StatusCodes } from 'http-status-codes'
import { ZodError, type AnyZodObject } from 'zod'

export function validate(schema: AnyZodObject) {
	return (req: Request, res: Response, next: NextFunction) => {
		try {
			schema.parse({
				body: req.body,
				query: req.query,
				params: req.params,
			})

			next()
		} catch (err: unknown) {
			if (err instanceof ZodError) {
				const errors = err.errors.map(error => {
					const { path, message } = error
					return { path, message }
				})

				return res.status(StatusCodes.BAD_REQUEST).json({
					status: 'error',
					errors,
				})
			}

			return res.status(StatusCodes.BAD_REQUEST).json({
				status: 'error',
				message: 'Invalid request data.',
			})
		}
	}
}
