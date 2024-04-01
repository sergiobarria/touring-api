import type { NextFunction, Request, Response } from 'express';
import { StatusCodes } from 'http-status-codes';
import { PostgresError } from 'postgres';

import type { CreateTourBody, GetTourParams } from './validators';
import * as services from './services';
import { logger } from '@/utils';

export async function checkTourIDHandler(req: Request, res: Response, next: NextFunction, id: string) {
	if (!Number(id)) {
		return res.status(StatusCodes.BAD_REQUEST).json({
			status: 'error',
			message: 'Tour ID must be a valid number',
		});
	}

	next();
}

export async function getToursHandler(req: Request, res: Response) {
	try {
		const tours = await services.getMany();

		return res.status(200).json({
			status: 'success',
			results: tours.length,
			data: { tours },
		});
	} catch (err: unknown) {
		logger.error('💥 Error fetching tours: ', err);
		return res.status(StatusCodes.INTERNAL_SERVER_ERROR).json({
			status: 'error',
			message: 'Internal server error',
		});
	}
}

export async function createTourHandler(req: Request<unknown, unknown, CreateTourBody>, res: Response) {
	try {
		const tour = await services.createOne(req.body);

		return res.status(StatusCodes.CREATED).json({
			status: 'success',
			message: 'Tour created successfully',
			data: { tour },
		});
	} catch (err: unknown) {
		logger.error('💥 Error creating tour: ', err);
		if (err instanceof PostgresError) {
			return res.status(StatusCodes.BAD_REQUEST).json({
				status: 'error',
				message: 'Internal server error',
				detail: err.detail || err.message,
			});
		}

		return res.status(StatusCodes.INTERNAL_SERVER_ERROR).json({
			status: 'error',
			message: 'Internal server error',
		});
	}
}

export async function getTourHandler(req: Request<GetTourParams>, res: Response) {
	try {
		const { id } = req.params;

		const tour = await services.getOne(Number(id));

		if (!tour) {
			return res.status(StatusCodes.NOT_FOUND).json({
				status: 'error',
				message: `Tour with ID: ${id} not found`,
			});
		}

		return res.status(StatusCodes.OK).json({
			status: 'success',
			message: 'Tour found successfully',
			data: { tour },
		});
	} catch (err: unknown) {
		logger.error('💥 Error fetching tour: ', err);
		return res.status(StatusCodes.INTERNAL_SERVER_ERROR).json({
			status: 'error',
			message: 'Internal server error',
		});
	}
}

export async function updateTourHandler(req: Request<GetTourParams>, res: Response) {
	try {
		const { id } = req.params;

		const tour = await services.updateOne(Number(id), req.body);

		if (!tour) {
			return res.status(StatusCodes.NOT_FOUND).json({
				status: 'error',
				message: `Tour with ID: ${id} not found`,
			});
		}

		return res.status(StatusCodes.OK).json({
			status: 'success',
			message: 'Tour updated successfully',
			data: { tour },
		});
	} catch (err: unknown) {}
}

export async function deleteTourHandler(req: Request<GetTourParams>, res: Response) {
	try {
		const { id } = req.params;

		const tourID = await services.deleteOne(Number(id));

		if (!tourID) {
			return res.status(StatusCodes.NOT_FOUND).json({
				status: 'error',
				message: `Tour with ID: ${id} not found`,
			});
		}

		return res.status(StatusCodes.OK).json({
			status: 'success',
			message: 'Tour deleted successfully',
		});
	} catch (err: unknown) {
		logger.error('💥 Error deleting tour: ', err);
		return res.status(StatusCodes.INTERNAL_SERVER_ERROR).json({
			status: 'error',
			message: 'Internal server error',
		});
	}
}
