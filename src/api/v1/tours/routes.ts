import express from 'express';

import * as controllers from './controllers';
import { getTourParamsSchema, insertTourSchema, updateTourSchema } from './validators';
import { validate } from '@/middlewares/validate.middleware';

const router = express.Router();

router.param('id', controllers.checkTourIDHandler);

router.route('/').get(controllers.getToursHandler).post(validate(insertTourSchema), controllers.createTourHandler);

router
	.route('/:id')
	.get(validate(getTourParamsSchema), controllers.getTourHandler)
	.patch(validate(updateTourSchema), controllers.updateTourHandler)
	.delete(validate(getTourParamsSchema), controllers.deleteTourHandler);

export { router as toursRouter };
