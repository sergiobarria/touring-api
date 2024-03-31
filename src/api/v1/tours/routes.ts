import express from 'express'

import * as controllers from './controllers'
import { getTourParamsSchema, createTourSchema } from './validators'
import { validate } from '@/middlewares/validate.middleware'

const router = express.Router()

router
	.route('/')
	.get(controllers.getToursHandler)
	.post(validate(createTourSchema), controllers.createTourHandler)

router
	.route('/:id')
	.get(validate(getTourParamsSchema), controllers.getTourHandler)
	.patch(validate(getTourParamsSchema), controllers.updateTourHandler)
	.delete(validate(getTourParamsSchema), controllers.deleteTourHandler)

export { router as toursRouter }
