<?php

namespace Tests\Feature\Screens\Panel;

use App\Application\Delivery\Actions\AddComment;
use App\Application\Delivery\Actions\ChangeDueTarget;
use App\Application\Delivery\Actions\ChangeReviewer;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\Deliverables\DeliverablePanel;
use App\Models\DueDateHistory;
use App\Models\Media;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\MediaLibraryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Media\FakesGoogleDrive;
use Tests\TestCase;

class PanelIntegrationActionsTest extends TestCase
{
    use FakesGoogleDrive, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-25 09:00:00'));
        Cache::forget(GoogleDriveService::TOKEN_CACHE_KEY);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function deliverable(DeliverableStatus $status = DeliverableStatus::IN_DESIGN, array $attributes = []): Deliverable
    {
        return Deliverable::factory()->for(WorkOrder::factory()->create(), 'workOrder')->inStatus($status)->create($attributes);
    }

    private function manager(): User
    {
        return User::factory()->coordinator()->create();
    }

    private function panel(User $user, Deliverable $deliverable): Testable
    {
        return Livewire::actingAs($user)->test(DeliverablePanel::class)->dispatch('open-deliverable', deliverableId: $deliverable->id);
    }

    private function connectDrive(): void
    {
        Http::preventStrayRequests();
        $this->configureGoogleDrive();
        Http::fake([
            'www.googleapis.com/upload/drive/v3/files?uploadType=resumable*' => Http::response([], 200, ['Location' => 'https://upload.example/session-1']),
            'www.googleapis.com/drive/v3/files/file-*' => fn (Request $request) => $this->driveFileMetadata($request),
        ]);
        Http::fake($this->googleDriveFakes());
    }

    private function driveFileMetadata(Request $request): mixed
    {
        $id = basename(parse_url($request->url(), PHP_URL_PATH));
        $file = $this->drive['files'][$id] ?? null;

        return $file === null
            ? Http::response([], 404)
            : Http::response(['id' => $id, 'name' => $file['name'], 'mimeType' => $file['mimeType'], 'size' => (string) strlen($file['content']), 'parents' => [$file['parent']], 'trashed' => $file['trashed']]);
    }

    public function test_only_managers_change_the_reviewer_and_the_new_reviewer_must_review(): void
    {
        $deliverable = $this->deliverable();
        $camila = User::factory()->designer()->reviewer()->create(['name' => 'Camila']);
        $plain = User::factory()->designer()->create();

        $this->assertTrue(app(ChangeReviewer::class)($deliverable, $camila, $this->manager()));
        $this->assertSame($camila->id, $deliverable->fresh()->reviewer_id);
        $event = $deliverable->events()->where('event_type', 'reviewer_changed')->latest('id')->first();
        $this->assertNull($event->previous_value);
        $this->assertSame('Camila', $event->new_value);

        $this->assertFalse(app(ChangeReviewer::class)($deliverable, $camila, $this->manager()));
        $this->assertTrue(app(ChangeReviewer::class)($deliverable, null, $this->manager()));
        $this->assertNull($deliverable->fresh()->reviewer_id);

        try {
            app(ChangeReviewer::class)($deliverable, $plain, $this->manager());
            $this->fail('A plain designer cannot review.');
        } catch (WorkflowException $exception) {
            $this->assertSame(__('Choose an active reviewer or manager.'), $exception->getMessage());
        }

        $this->expectException(AuthorizationException::class);
        app(ChangeReviewer::class)($deliverable, $camila, $plain);
    }

    public function test_the_due_target_change_keeps_a_history_and_follows_the_rules(): void
    {
        $deliverable = $this->deliverable(attributes: ['due_target' => '2026-10-01']);
        $manager = $this->manager();

        $this->assertTrue(app(ChangeDueTarget::class)($deliverable, Carbon::parse('2026-10-05'), 'Client asked for more time', $manager));

        $this->assertSame('2026-10-05', $deliverable->fresh()->due_target->toDateString());
        $history = DueDateHistory::query()->where('order_id', $deliverable->id)->sole();
        $this->assertSame('2026-10-01', $history->previous_due_date->toDateString());
        $this->assertSame('2026-10-05', $history->new_due_date->toDateString());
        $this->assertSame('Client asked for more time', $history->reason);
        $this->assertSame(ChangeDueTarget::TRIGGER, $history->trigger_event);
        $this->assertSame($manager->name, $history->created_by);
        $this->assertSame('2026-10-05', Order::query()->find($deliverable->id)->current_due_date->toDateString());
        $this->assertSame('2026-10-05', $deliverable->events()->where('event_type', 'due_target_changed')->sole()->new_value);

        $this->assertFalse(app(ChangeDueTarget::class)($deliverable, Carbon::parse('2026-10-05'), null, $manager));
        $this->assertSame(1, DueDateHistory::query()->count());

        $reviewer = User::factory()->designer()->reviewer()->create();
        $this->assertTrue(app(ChangeDueTarget::class)($deliverable, Carbon::parse('2026-10-07'), null, $reviewer));

        $this->expectException(AuthorizationException::class);
        app(ChangeDueTarget::class)($deliverable, Carbon::parse('2026-10-09'), null, User::factory()->designer()->create());
    }

    public function test_a_closed_deliverable_keeps_its_due_target(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::DONE, ['due_target' => '2026-10-01']);

        $this->expectException(WorkflowException::class);
        app(ChangeDueTarget::class)($deliverable, Carbon::parse('2026-10-05'), null, $this->manager());
    }

    public function test_the_panel_fields_change_reviewer_and_due_target_through_the_actions(): void
    {
        $deliverable = $this->deliverable(attributes: ['due_target' => '2026-10-01']);
        $camila = User::factory()->designer()->reviewer()->create(['name' => 'Camila']);

        $this->panel($this->manager(), $deliverable)
            ->assertSeeHtml('data-field="reviewer"')
            ->assertSeeHtml('data-field="due-target"')
            ->call('changeReviewer', $camila->id)
            ->assertSet('noticeText', 'Reviewer changed.')
            ->assertDispatched('deliverable-changed', deliverableId: $deliverable->id)
            ->call('changeDueTarget', '2026-10-08', '')
            ->assertSet('noticeText', 'Due date changed.')
            ->call('changeDueTarget', 'not a date')
            ->assertSet('noticeTone', 'danger');

        $this->assertSame($camila->id, $deliverable->fresh()->reviewer_id);
        $this->assertSame('2026-10-08', $deliverable->fresh()->due_target->toDateString());

        $this->panel(User::factory()->designer()->create(), $deliverable)
            ->assertDontSeeHtml('data-field="reviewer"')
            ->assertDontSeeHtml('data-field="due-target"')
            ->call('changeReviewer', null)
            ->assertSet('noticeText', 'You are not allowed to do that.');
    }

    public function test_posting_a_comment_saves_it_natively_and_shows_it(): void
    {
        $deliverable = $this->deliverable();
        $designer = User::factory()->designer()->create(['name' => 'Euraliz']);

        $this->panel($designer, $deliverable)
            ->call('showTab', 'comments')
            ->assertSeeHtml('data-comment-form')
            ->set('newComment', '   ')
            ->call('addComment')
            ->assertSet('noticeText', 'Write a comment first.')
            ->set('newComment', 'The client sent the vector logo')
            ->call('addComment')
            ->assertSet('newComment', '')
            ->assertSee('The client sent the vector logo')
            ->assertSee('Euraliz')
            ->assertNotDispatched('deliverable-changed');

        $comment = OrderComment::query()->where('order_id', $deliverable->id)->sole();
        $this->assertSame($designer->id, $comment->user_id);
        $this->assertNull($comment->trello_action_id);
        $this->assertSame(1, $deliverable->events()->where('event_type', 'comment_added')->count());
    }

    public function test_a_comment_cannot_be_too_long(): void
    {
        $this->expectException(WorkflowException::class);
        app(AddComment::class)($this->deliverable(), str_repeat('a', AddComment::MAX_LENGTH + 1), $this->manager());
    }

    public function test_attaching_a_file_without_google_drive_shows_the_translated_message(): void
    {
        Http::preventStrayRequests();
        $deliverable = $this->deliverable();
        $message = 'Google Drive is not connected. Connect it in Settings → Google Drive.';

        $this->panel($this->manager(), $deliverable)
            ->call('showTab', 'comments')
            ->assertSeeHtml('data-file-input')
            ->call('prepareUpload', 'logo.pdf', 'application/pdf', 1200)
            ->assertSet('noticeText', $message)
            ->assertSet('noticeTone', 'danger');

        app()->setLocale('es');
        $this->panel($this->manager(), $deliverable)
            ->call('prepareUpload', 'logo.pdf', 'application/pdf', 1200)
            ->assertSet('noticeText', __($message));
        $this->assertNotSame($message, __($message));
        $this->assertSame(0, Media::query()->count());
    }

    public function test_a_file_goes_to_the_drive_folder_of_the_deliverable_and_is_recorded(): void
    {
        $this->connectDrive();
        $deliverable = $this->deliverable();
        $user = $this->manager();

        $this->panel($user, $deliverable)
            ->call('prepareUpload', 'proof.pdf', 'application/pdf', 4)
            ->assertReturned(['url' => 'https://upload.example/session-1']);

        $folderId = app(MediaLibraryService::class)->folderForOrder(Order::query()->findOrFail($deliverable->id));
        $uploaded = app(GoogleDriveService::class)->uploadFile('DATA', 'proof.pdf', 'application/pdf', $folderId);

        $this->panel($user, $deliverable)
            ->call('showTab', 'comments')
            ->call('finishUpload', $uploaded['id'])
            ->assertSet('noticeText', 'File attached.')
            ->assertSee('proof.pdf');

        $media = Media::query()->where('order_id', $deliverable->id)->sole();
        $this->assertSame($uploaded['id'], $media->drive_file_id);
        $this->assertSame($folderId, $media->drive_folder_id);
        $this->assertSame($user->id, $media->uploaded_by);
        $this->assertSame(Media::SOURCE_UPLOAD, $media->source);
        $this->assertSame(1, $deliverable->events()->where('event_type', 'file_attached')->count());

        $this->panel($user, $deliverable)->call('finishUpload', $uploaded['id']);
        $this->assertSame(1, Media::query()->count());

        $this->actingAs($user)->get(route('media.file', $media))->assertOk();
    }

    public function test_a_drive_file_from_another_folder_cannot_be_attached(): void
    {
        $this->connectDrive();
        $deliverable = $this->deliverable();
        $stranger = app(GoogleDriveService::class)->uploadFile('SECRET', 'secret.pdf', 'application/pdf', 'some-other-folder');

        $this->panel($this->manager(), $deliverable)
            ->call('finishUpload', $stranger['id'])
            ->assertSet('noticeTone', 'danger');

        $this->assertSame(0, Media::query()->count());
    }

    public function test_the_upload_size_is_checked_before_a_session_is_opened(): void
    {
        $this->connectDrive();

        $this->panel($this->manager(), $this->deliverable())
            ->call('prepareUpload', 'huge.mov', 'video/quicktime', 600 * 1048576)
            ->assertSet('noticeTone', 'danger');
    }
}
