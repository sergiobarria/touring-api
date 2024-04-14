from rest_framework import status
from rest_framework.response import Response


class APIResponse(Response):
    """Custom API Response class to return response in a consistent format."""

    def __init__(
        self,
        success=True,
        data=None,
        status_code=status.HTTP_200_OK,
        message=None,
        errors=None,
        results=None,
        headers=None,
        **kwargs,
    ):
        response_data = {"success": success, "code": status_code}

        if message is not None:
            response_data["message"] = message

        if results is not None:
            response_data["results"] = results

        if errors:
            response_data["errors"] = errors

        if data is not None:
            response_data["data"] = data

        # Check for HTTP 204 No Content and handle it separately
        if status_code == status.HTTP_204_NO_CONTENT:
            response_data = None

        return super().__init__(data=response_data, status=status_code, headers=headers, **kwargs)
