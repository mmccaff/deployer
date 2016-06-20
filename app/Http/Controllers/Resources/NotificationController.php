<?php

namespace REBELinBLUE\Deployer\Http\Controllers\Resources;

use REBELinBLUE\Deployer\Contracts\Repositories\NotificationRepositoryInterface;
use REBELinBLUE\Deployer\Http\Requests\StoreNotificationRequest;

/**
 * Controller for managing notifications.
 */
class NotificationController extends ResourceController
{
    /**
     * Class constructor.
     *
     * @param  NotificationRepositoryInterface $repository
     * @return void
     */
    public function __construct(NotificationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Store a newly created notification in storage.
     *
     * @param  StoreNotificationRequest $request
     * @return \Illuminate\View\View
     */
    public function store(StoreNotificationRequest $request)
    {
        return $this->repository->create($request->only(
            'name',
            'channel',
            'webhook',
            'project_id',
            'icon',
            'failure_only'
        ));
    }

    /**
     * Update the specified notification in storage.
     *
     * @param  int                      $notification_id
     * @param  StoreNotificationRequest $request
     * @return \Illuminate\View\View
     */
    public function update($notification_id, StoreNotificationRequest $request)
    {
        return $this->repository->updateById($request->only(
            'name',
            'channel',
            'webhook',
            'icon',
            'failure_only'
        ), $notification_id);
    }
}
