<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;
use DTApi\Http\Requests\StoreJobRequest;
use DTApi\Http\Requests\UpdateJobRequest;
use Illuminate\Http\JsonResponse;

/**
 * Class BookingController
 * 
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{
    protected BookingRepository $repository;

    /**
     * BookingController constructor.
     * 
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * List of the authenticated user's jobs or all jobs based on user's role
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $response = [];

        if ($user_id = $request->get('user_id')) {
            $response = $this->repository->getUsersJobs($user_id);
        } elseif (in_array($request->user()->user_type, [config('roles.admin'), config('roles.superadmin')])) {
            $response = $this->repository->getAll($request);
        }

        return response()->json($response);
    }

    /**
     * Get specific job with translator details.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);
        return response()->json($job);
    }

    /**
     * Store a newly created job.
     *
     * @param StoreJobRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreJobRequest $request): JsonResponse
    {
        $response = $this->repository->store($request->user(), $request->validated());
        return response()->json($response);
    }

    /**
     * Update the specified job.
     *
     * @param int $id
     * @param UpdateJobRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, UpdateJobRequest $request): JsonResponse
    {
        $response = $this->repository->updateJob($id, $request->except(['_token', 'submit']), $request->user());
        return response()->json($response);
    }

    /**
     * Store job related data and sends an email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function immediateJobEmail(Request $request): JsonResponse
    {
        $response = $this->repository->storeAndSendJobEmail($request->validated());
        return response()->json($response);
    }

    /**
     * Get the history of the user's jobs.
     *
     * @param Request $request
     * @return JsonResponse|null
     */
    public function getHistory(Request $request): ?JsonResponse
    {
        if ($user_id = $request->get('user_id')) {
            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response()->json($response);
        }

        return response()->json([], 204);
    }

    /**
     * Accept a job for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function acceptJob(Request $request): JsonResponse
    {
        $response = $this->repository->acceptJob($request->validated(), $request->user());
        return response()->json($response);
    }

    /**
     * Accept a specified job for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function acceptJobWithId(Request $request): JsonResponse
    {
        $response = $this->repository->acceptJobWithId($request->get('job_id'), $request->user());
        return response()->json($response);
    }

    /**
     * Cancel a job for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cancelJob(Request $request): JsonResponse
    {
        $response = $this->repository->cancelJobAjax($request->validated(), $request->user());
        return response()->json($response);
    }

    /**
     * End a job.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function endJob(Request $request): JsonResponse
    {
        $response = $this->repository->endJob($request->validated());
        return response()->json($response);
    }

    /**
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function customerNotCall(Request $request): JsonResponse
    {
        $response = $this->repository->customerNotCall($request->validated());
        return response()->json($response);
    }

    /**
     * Get potential jobs for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPotentialJobs(Request $request): JsonResponse
    {
        $response = $this->repository->getPotentialJobs($request->user());
        return response()->json($response);
    }


    /**
     * Update distance and other job-related fields.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function distanceFeed(Request $request): JsonResponse
    {
        $data = $request->only(['distance', 'time', 'jobid', 'session_time', 'flagged', 'manually_handled', 'by_admin', 'admincomment']);

        $this->updateDistance($data);
        $this->updateJobFields($data);

        return response()->json('Record updated!');
    }

    /**
     * Update the distance & time for a job.
     *
     * @param array $data
     * @return void
     */
    private function updateDistance(array $data): void
    {
        if (!empty($data['distance']) || !empty($data['time'])) {
            Distance::where('job_id', $data['jobid'])->update([
                'distance' => $data['distance'],
                'time' => $data['time']
            ]);
        }
    }

    /**
     * Update the job fields
     *
     * @param array $data
     * @return void
     */
    private function updateJobFields(array $data): void
    {
        $updates = array_filter([
            'admin_comments' => $data['admincomment'] ?? '',
            'flagged' => ($data['flagged'] === 'true') ? 'yes' : 'no',
            'session_time' => $data['session_time'] ?? '',
            'manually_handled' => ($data['manually_handled'] === 'true') ? 'yes' : 'no',
            'by_admin' => ($data['by_admin'] === 'true') ? 'yes' : 'no'
        ]);

        if (!empty($updates)) {
            Job::where('id', $data['jobid'])->update($updates);
        }
    }

    /**
     * Reopen a job.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reopen(Request $request): JsonResponse
    {
        $response = $this->repository->reopen($request->validated());
        return response()->json($response);
    }

    /**
     * Resend notifications to translators for a job.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resendNotifications(Request $request): JsonResponse
    {
        $job = $this->repository->find($request->get('jobid'));
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response()->json(['success' => 'Push sent']);
    }

    /**
     * Resend SMS notifications to translators for a job.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resendSMSNotifications(Request $request): JsonResponse
    {
        $job = $this->repository->find($request->get('jobid'));

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response()->json(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
