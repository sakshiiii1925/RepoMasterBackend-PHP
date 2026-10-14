<?php
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../helpers/response.php';

class SearchHistoryController
{
    public function __construct(
        private SearchHistoryService $s,
        private UserService $userService
    ) {}

    public function save()
    {
        jsonResponse(
            $this->s->save(
                (string) queryParam('vehicleNumber', ''),
                (string) queryParam('userEmail', ''),
                (string) queryParam('userName', ''),
                (string) queryParam('agencyId', '')
            )
        );
    }

    public function list()
    {
        jsonResponse(
            $this->s->list(
                (string) queryParam('agencyId', '')
            )
        );
    }

    public function all()
    {
        jsonResponse(
            $this->s->all()
        );
    }

    public function search()
    {
        jsonResponse(
            $this->s->search(
                (string) queryParam('agencyId', ''),
                (string) queryParam('vehicleNumber', '')
            )
        );
    }

    public function user()
    {
        jsonResponse(
            $this->s->byUser(
                (string) queryParam('agencyId', ''),
                (string) queryParam('userName', '')
            )
        );
    }

    public function date()
    {
        jsonResponse(
            $this->s->byDate(
                (string) queryParam('agencyId', ''),
                (string) queryParam('date', '')
            )
        );
    }

    public function sort()
    {
        jsonResponse(
            $this->s->sort(
                (string) queryParam('agencyId', ''),
                (string) queryParam('order', '')
            )
        );
    }

    // =========================================================
    // DELETE SEARCH HISTORY
    // =========================================================

    public function delete($id)
    {
        $userId =
            (int) queryParam('userId', 0);

        if ($userId <= 0) {

            errorResponse(
                'userId is required',
                400
            );

            return;
        }

        $agencyId =
            $this->userService
                ->getUserAgencyId($userId);

        if (!$agencyId) {

            errorResponse(
                'Agency not found for user',
                404
            );

            return;
        }

        $deleted =
            $this->s->delete(
                (int) $id,
                $agencyId
            );

        if (!$deleted) {

            errorResponse(
                'Search history not found',
                404
            );

            return;
        }

        jsonResponse([
            'success' => true,
            'message' =>
                'Search history deleted successfully'
        ]);
    }
   public function deleteMultiple()
{
    // Read the JSON request body.
    $body = requestBody();

    // Support userId from JSON body or query parameters.
    $userId = (int) (
        $body['userId']
        ?? queryParam('userId', 0)
    );

    if ($userId <= 0) {
        errorResponse('userId is required', 400);
        return;
    }

    // Get selected history record IDs.
    $ids = $body['ids'] ?? [];

    if (!is_array($ids) || empty($ids)) {
        errorResponse('No search history IDs received', 400);
        return;
    }

    // Validate IDs before deleting anything.
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $ids),
        fn($id) => $id > 0
    )));

    if (empty($ids)) {
        errorResponse('No valid search history IDs received', 400);
        return;
    }

    // Find the user's agency.
    $agencyId = $this->userService->getUserAgencyId($userId);

    if (!$agencyId) {
        errorResponse('Agency not found for user', 404);
        return;
    }

    // Delete only records belonging to that agency.
    $deleted = $this->s->deleteMultiple(
        $ids,
        (string) $agencyId
    );

    jsonResponse([
        'success' => true,
        'message' => $deleted .
            ' search history record(s) deleted successfully',
        'deletedCount' => $deleted
    ]);
}
}