<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * A one-time welcome notification telling the user their personal onboarding code —
 * the identifier others use to add them to an organisation on the platform, so nobody
 * has to share an email address to be invited.
 */
class OnboardingCodeNotification extends Notification
{
    public function __construct(public string $code) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'onboarding_code',
            'code' => $this->code,
            'message' => __('Your onboarding code is :code. Share it so an organisation can add you to their team on the platform.', ['code' => $this->code]),
        ];
    }
}
