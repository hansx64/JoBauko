<?php

namespace App\Enums;

/**
 * The SMS messages JoBauko sends. Administrators can reword each template;
 * the placeholders available to it are fixed here.
 */
enum SmsTemplateKey: string
{
    case ApplicationStatus = 'application_status';
    case DeadlineReminder = 'deadline_reminder';
    case NewOpportunity = 'new_opportunity';
    case Announcement = 'announcement';

    public function label(): string
    {
        return match ($this) {
            self::ApplicationStatus => 'Application status update',
            self::DeadlineReminder => 'Deadline reminder',
            self::NewOpportunity => 'New matching opportunity',
            self::Announcement => 'Announcement',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ApplicationStatus => 'Sent to an applicant when an organization changes the status of their application.',
            self::DeadlineReminder => 'Sent three days before the deadline of an opportunity the seeker saved but has not applied to.',
            self::NewOpportunity => 'Sent when a newly published opportunity is a strong match for a seeker who opted into its topic.',
            self::Announcement => 'Sent with administrator announcements to recipients who chose SMS for announcements.',
        };
    }

    /**
     * @return list<string>
     */
    public function placeholders(): array
    {
        return match ($this) {
            self::ApplicationStatus => ['name', 'opportunity', 'status', 'remarks'],
            self::DeadlineReminder => ['name', 'opportunity', 'deadline'],
            self::NewOpportunity => ['name', 'opportunity', 'organization', 'deadline'],
            self::Announcement => ['name', 'title', 'message'],
        };
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::ApplicationStatus => 'JoBauko: Hi {name}, your application for {opportunity} is now {status}. {remarks}',
            self::DeadlineReminder => 'JoBauko: Hi {name}, applications for {opportunity} close on {deadline}. Apply on JoBauko before the deadline.',
            self::NewOpportunity => 'JoBauko: New opportunity for you: {opportunity} by {organization}. Apply by {deadline} on JoBauko.',
            self::Announcement => 'JoBauko: {title}. {message}',
        };
    }
}
