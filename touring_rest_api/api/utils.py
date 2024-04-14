from rest_framework import status
from rest_framework.response import Response


class APIResponse(Response):
    """Custom API Response class to return response in a consistent format"""

    def __init__(
        self, data=None, status=status.HTTP_200_OK, message=None, errors=None, results=None, headers=None, **kwargs
    ):
        response_data = {"success": True, "code": status}

        if message is not None:
            response_data["message"] = message

        if results is not None:
            response_data["results"] = results

        if errors:
            response_data["errors"] = errors

        if data is not None:
            response_data["data"] = data

        return super().__init__(data=response_data, status=status, headers=headers, **kwargs)
