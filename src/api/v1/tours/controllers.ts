import fs from 'node:fs'
import path from 'node:path'

import type { Request, Response } from 'express'
import { StatusCodes } from 'http-status-codes'
import type { CreateTourBody, GetTourParams } from './validators'

const tours = fs.readFileSync(path.join(__dirname, '../../../../data/tours-simple.json'), 'utf-8')

export async function getToursHandler(req: Request, res: Response) {
	res.status(200).json({
		status: 'success',
		results: JSON.parse(tours).length,
		data: {
			tours: JSON.parse(tours),
		},
	})
}

export async function createTourHandler(
	req: Request<unknown, unknown, CreateTourBody>,
	res: Response
) {
	res.status(StatusCodes.NOT_IMPLEMENTED).json({
		status: 'error',
		message: 'This route is not yet implemented! Please try again later.',
	})
}

export async function getTourHandler(req: Request<GetTourParams>, res: Response) {
	const { id } = req.params

	res.status(StatusCodes.NOT_IMPLEMENTED).json({
		status: 'error',
		message: 'This route is not yet implemented! Please try again later.',
		id,
	})
}

export async function updateTourHandler(req: Request<GetTourParams>, res: Response) {
	const { id } = req.params

	res.status(StatusCodes.NOT_IMPLEMENTED).json({
		status: 'error',
		message: 'This route is not yet implemented! Please try again later.',
		id,
	})
}

export async function deleteTourHandler(req: Request<GetTourParams>, res: Response) {
	const { id } = req.params

	res.status(StatusCodes.NOT_IMPLEMENTED).json({
		status: 'error',
		message: 'This route is not yet implemented! Please try again later.',
		id,
	})
}
