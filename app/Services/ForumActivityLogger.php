<?php

namespace App\Services;

use App\Models\ForumActivityLog;
use App\Models\ForumComment;
use App\Models\ForumSection;
use App\Models\ForumThread;
use App\Models\User;

class ForumActivityLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        ?User $actor = null,
        ForumSection|ForumThread|ForumComment|null $target = null,
        ?string $summary = null,
        array $metadata = [],
        ?User $targetUser = null,
    ): ForumActivityLog {
        $section = null;
        $thread = null;
        $comment = null;

        if ($target instanceof ForumSection) {
            $section = $target;
        }

        if ($target instanceof ForumThread) {
            $thread = $target;
            $section = $target->section;
            $targetUser ??= $target->user;
        }

        if ($target instanceof ForumComment) {
            $comment = $target;
            $thread = $target->thread;
            $section = $thread->section;
            $targetUser ??= $target->user;
        }

        return ForumActivityLog::query()->create([
            'forum_section_id' => $section?->id,
            'forum_thread_id' => $thread?->id,
            'forum_comment_id' => $comment?->id,
            'actor_user_id' => $actor?->id,
            'target_user_id' => $targetUser?->id,
            'action' => $action,
            'target_type' => $target ? class_basename($target) : null,
            'summary' => $summary,
            'metadata' => $metadata,
        ]);
    }
}
