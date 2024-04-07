from ninja import Router

router = Router()


@router.get("/")
def list_tours(request):
    return {"message": "List of tours"}


@router.post("/")
def create_tour(request):
    return {"message": "Create a new tour"}


@router.get("/{tour_id}")
def retrieve_tour(request, tour_id: int):
    return {"message": f"Retrieve tour with id {tour_id}"}


@router.put("/{tour_id}")
def update_tour(request, tour_id: int):
    return {"message": f"Update tour with id {tour_id}"}


@router.delete("/{tour_id}")
def delete_tour(request, tour_id: int):
    return {"message": f"Delete tour with id {tour_id}"}
