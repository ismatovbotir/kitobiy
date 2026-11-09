<?php

namespace Tests\Feature;

use App\Enums\EditionSource;
use App\Models\Edition;
use App\Models\Post;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_readers_see_published_news_only(): void
    {
        $published = Post::factory()->create(['title_uz' => 'Kitobiy ochildi', 'body_uz' => "Birinchi xabar.\n\n**Muhim** qism."]);
        $draft = Post::factory()->draft()->create(['title_uz' => 'Qoralama']);
        $scheduled = Post::factory()->scheduled()->create(['title_uz' => 'Kelgusi xabar']);

        $this->get(route('news.index'))->assertOk()->assertSee('Kitobiy ochildi')->assertDontSee('Qoralama')->assertDontSee('Kelgusi xabar');
        $this->get($published->publicUrl())
            ->assertOk()
            ->assertSee('<strong>Muhim</strong>', false)
            ->assertSee('"@type":"NewsArticle"', false)
            ->assertDontSee('noindex');
        $this->get($draft->publicUrl())->assertNotFound();
        $this->get($scheduled->publicUrl())->assertNotFound();

        $this->travel(8)->days();
        $this->get($scheduled->publicUrl())->assertOk();
        $this->get(route('sitemap'))->assertSee($published->publicUrl())->assertSee($scheduled->publicUrl())->assertDontSee($draft->publicUrl());
    }

    public function test_russian_readers_get_the_russian_text_when_there_is_one(): void
    {
        $post = Post::factory()->create(['title_uz' => 'Yangilik', 'title_ru' => 'Новость', 'body_uz' => 'Matn', 'body_ru' => 'Текст']);
        $onlyUzbek = Post::factory()->create(['title_uz' => 'Faqat o‘zbekcha']);

        app()->setLocale('ru');

        $this->assertSame('Новость', $post->title);
        $this->assertSame('Faqat o‘zbekcha', $onlyUzbek->title);
        app()->setLocale('uz');
        $this->assertSame('Yangilik', $post->title);
    }

    public function test_raw_html_and_script_links_in_the_text_are_not_rendered(): void
    {
        $post = Post::factory()->create(['body_uz' => "<script>alert(1)</script>\n\n[bos](javascript:alert(1))"]);

        $this->get($post->publicUrl())->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('href="javascript:', false);
    }

    public function test_super_admins_write_preview_and_publish_a_post(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $publisher = Publisher::factory()->create(['name' => 'Sharq']);
        $edition = Edition::factory()->for($publisher)->create(['isbn_13' => '9789943000001', 'source' => EditionSource::Publisher]);

        $this->actingAs(User::factory()->create())->get(route('admin.news.create'))->assertForbidden();

        Livewire::actingAs($admin)->test('pages::admin.news.edit')
            ->set('titleUz', 'Sharq Kitobiy’ga qo‘shildi')
            ->set('bodyUz', 'Matn')
            ->set('publisherName', 'Sharq')
            ->set('bookIsbn', '978-9943-000-00-1')
            ->set('image', UploadedFile::fake()->image('cover.jpg', 1300, 600))
            ->call('save')
            ->assertHasNoErrors();

        $post = Post::query()->sole();
        $this->assertSame('sharq-kitobiyga-qoshildi', $post->slug);
        $this->assertNull($post->published_at);
        $this->assertTrue($post->publisher->is($publisher));
        $this->assertTrue($post->edition->is($edition));
        Storage::disk('public')->assertExists($post->image_path);
        $this->actingAs($admin)->get($post->publicUrl())->assertOk()->assertSee(__('Draft: only super admins see this page.'));

        Livewire::actingAs($admin)->test('pages::admin.news.edit', ['post' => $post])
            ->set('titleUz', 'Sharq endi Kitobiy’da')
            ->call('publishNow')
            ->assertHasNoErrors();

        $post->refresh();
        $this->assertTrue($post->isPublished());
        $this->assertSame('sharq-endi-kitobiyda', $post->slug);

        Livewire::actingAs($admin)->test('pages::admin.news.edit', ['post' => $post])->set('titleUz', 'Boshqa sarlavha')->call('save');
        $this->assertSame('sharq-endi-kitobiyda', $post->fresh()->slug, 'A published address never changes.');

        auth()->logout();
        $this->get($publisher->publicUrl())->assertSee('Boshqa sarlavha');
        $this->get($edition->publicUrl())->assertSee('Boshqa sarlavha');
    }

    public function test_the_post_form_checks_its_fields(): void
    {
        Livewire::actingAs($this->admin())->test('pages::admin.news.edit')
            ->set('titleUz', '')
            ->set('titleRu', 'Только заголовок')
            ->set('publisherName', 'Unknown house')
            ->set('authorName', 'Nobody Known')
            ->set('bookIsbn', '9789943000001')
            ->call('save')
            ->assertHasErrors(['titleUz', 'bodyUz', 'bodyRu']);

        Livewire::actingAs($this->admin())->test('pages::admin.news.edit')
            ->set('titleUz', 'Sarlavha')
            ->set('bodyUz', 'Matn')
            ->set('publisherName', 'Unknown house')
            ->set('authorName', 'Nobody Known')
            ->set('bookIsbn', '9789943000001')
            ->call('save')
            ->assertHasErrors(['publisherName', 'authorName', 'bookIsbn']);

        $this->assertSame(0, Post::count());
    }

    public function test_titles_that_repeat_get_their_own_address(): void
    {
        Post::factory()->create(['slug' => 'yangilik']);

        $this->assertSame('yangilik-2', Post::freeSlug('Yangilik'));
    }

    public function test_a_super_admin_deletes_a_post(): void
    {
        $post = Post::factory()->create();

        Livewire::actingAs($this->admin())->test('pages::admin.news.edit', ['post' => $post])
            ->call('delete')
            ->assertRedirect(route('admin.news.index'));

        $this->assertSame(0, Post::count());
    }

    public function test_approving_a_publishers_first_member_drafts_a_joined_post(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $publisher = Publisher::factory()->withMember(User::factory()->create(), approved: false)->withMember(User::factory()->create(), approved: false)->create(['name' => 'Sharq']);
        $memberIds = $publisher->members()->get()->pluck('membership.id');

        $page = Livewire::actingAs($admin)->test('pages::admin.publishers.claims');
        $page->call('approve', $memberIds[0])->assertSee(__('Open the draft'));
        $page->call('approve', $memberIds[1]);

        $post = Post::query()->sole();
        $this->assertNull($post->published_at);
        $this->assertTrue($post->publisher->is($publisher));
        $this->assertSame(__(':publisher joined Kitobiy', ['publisher' => 'Sharq'], 'uz'), $post->title_uz);
        $this->assertSame(__(':publisher joined Kitobiy', ['publisher' => 'Sharq'], 'ru'), $post->title_ru);
        $this->assertStringContainsString($publisher->publicUrl(), $post->body_uz);
    }
}
