<?php

namespace App\Services\Storefront;

use App\BusinessLocation;
use App\StorefrontCommunityPost;
use App\StorefrontCommunityPostMedia;
use App\StorefrontCommunityPostTranslation;
use Illuminate\Support\Facades\DB;

/**
 * Demo Community CMS posts (news / tournaments / events) for storefront UI QA.
 * Identified by slug prefix {@see self::SLUG_PREFIX} so they can be wiped safely.
 */
class CommunityDemoContent
{
    public const SLUG_PREFIX = 'demo-';

    /** @var list<string> */
    public const IMAGES = [
        'https://pos.gamesspoteg.com/uploads/storefront_library/1/1789823583_6bmjghiz_The-Rise-of-Major-Gaming-Events-in-the-World_COVER.jpg',
        'https://pos.gamesspoteg.com/uploads/storefront_library/1/1789823580_1rllnhwg_images.jpg',
        'https://pos.gamesspoteg.com/uploads/storefront_library/1/1789823577_ajd9bkce_imagasdes.jpg',
        'https://pos.gamesspoteg.com/uploads/storefront_library/1/1789823575_9tm9qzl4_imadsadges.jpg',
        'https://pos.gamesspoteg.com/uploads/storefront_library/1/1789823571_bx3stmxy_68124103c518b98b2f991011_top-11-wildest.jpg',
        'https://pos.gamesspoteg.com/uploads/storefront_library/1/1789735771_olgxxd5h_games-spot-slider-3.webp',
        'https://pos.gamesspoteg.com/uploads/storefront_library/1/1789734092_fovlx8oh_games-spot-slider-1.webp',
    ];

    /**
     * Force-delete all demo community posts (and cascaded media/applications) for a business.
     */
    public function wipe(int $businessId): int
    {
        $ids = StorefrontCommunityPost::withTrashed()
            ->where('business_id', $businessId)
            ->where('slug', 'like', self::SLUG_PREFIX.'%')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        // Media/applications cascade on hard delete of posts.
        return StorefrontCommunityPost::withTrashed()
            ->whereIn('id', $ids)
            ->forceDelete();
    }

    /**
     * Replace demo posts for the business with a full content set.
     *
     * @return int Number of posts created
     */
    public function seed(int $businessId): int
    {
        $this->wipe($businessId);

        $locationId = BusinessLocation::where('business_id', $businessId)
            ->where('is_active', 1)
            ->orderBy('id')
            ->value('id');

        $defs = $this->definitions($locationId ? (int) $locationId : null);
        $created = 0;

        DB::transaction(function () use ($businessId, $defs, &$created) {
            foreach ($defs as $def) {
                $this->createPost($businessId, $def);
                $created++;
            }
        });

        return $created;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitions(?int $locationId): array
    {
        $img = self::IMAGES;

        return [
            // —— Gaming news ——
            [
                'type' => StorefrontCommunityPost::TYPE_NEWS,
                'slug' => self::SLUG_PREFIX.'news-rise-of-gaming-events',
                'cover' => $img[0],
                'gallery' => [$img[1], $img[2]],
                'is_featured' => true,
                'published_at' => now()->subDays(2),
                'en' => [
                    'title' => 'The Rise of Major Gaming Events',
                    'excerpt' => 'How tournaments and fan festivals reshaped the Egyptian gaming scene.',
                    'body' => '<p>From packed arenas to livestream audiences, major gaming events are becoming a fixture of youth culture. Games Spot continues to host meetups that bring players, creators, and brands together.</p><p>This guide covers what to expect at our next community nights and how to get involved.</p>',
                ],
                'ar' => [
                    'title' => 'صعود فعاليات الألعاب الكبرى',
                    'excerpt' => 'كيف أعادت البطولات والمهرجانات تشكيل مشهد الألعاب في مصر.',
                    'body' => '<p>من القاعات المزدحمة إلى بثوث البث المباشر، أصبحت فعاليات الألعاب جزءاً أساسياً من ثقافة الشباب. تواصل Games Spot استضافة لقاءات تجمع اللاعبين والمبدعين والعلامات.</p>',
                ],
            ],
            [
                'type' => StorefrontCommunityPost::TYPE_NEWS,
                'slug' => self::SLUG_PREFIX.'news-ps5-tips',
                'cover' => $img[5],
                'gallery' => [$img[6], $img[3]],
                'is_featured' => true,
                'published_at' => now()->subDays(5),
                'en' => [
                    'title' => 'PS5 Setup Tips for Competitive Play',
                    'excerpt' => 'Latency, storage, and display settings that matter on match day.',
                    'body' => '<p>Small tweaks can make a big difference. We recommend a wired connection, Performance Mode where available, and a monitor with low response times.</p>',
                ],
                'ar' => [
                    'title' => 'نصائح إعداد PS5 للعب التنافسي',
                    'excerpt' => 'زمن الاستجابة والتخزين وإعدادات الشاشة التي تهم يوم المباراة.',
                    'body' => '<p>تعديلات بسيطة تصنع فرقاً كبيراً. ننصح باتصال سلكي ووضع الأداء عند التوفر وشاشة بزمن استجابة منخفض.</p>',
                ],
            ],
            [
                'type' => StorefrontCommunityPost::TYPE_NEWS,
                'slug' => self::SLUG_PREFIX.'news-community-recap',
                'cover' => $img[4],
                'gallery' => [$img[1]],
                'is_featured' => false,
                'published_at' => now()->subDays(10),
                'en' => [
                    'title' => 'Community Night Recap: Controllers & Coffee',
                    'excerpt' => 'Highlights from our latest open-play evening at the branch.',
                    'body' => '<p>Thanks to everyone who showed up for casual matches, giveaways, and hangouts. Next open-play date will be announced soon.</p>',
                ],
                'ar' => [
                    'title' => 'ملخص ليلة المجتمع: أجهزة وتحكّم وقهوة',
                    'excerpt' => 'أبرز لحظات أمسية اللعب المفتوح الأخيرة في الفرع.',
                    'body' => '<p>شكراً لكل من حضر المباريات الودية والجوائز واللقاءات. سنعلن موعد اللعب المفتوح القادم قريباً.</p>',
                ],
            ],
            [
                'type' => StorefrontCommunityPost::TYPE_NEWS,
                'slug' => self::SLUG_PREFIX.'news-gift-card-guide',
                'cover' => $img[6],
                'gallery' => [$img[5], $img[2]],
                'is_featured' => false,
                'published_at' => now()->subDays(14),
                'en' => [
                    'title' => 'Digital Gift Cards: What to Know Before You Buy',
                    'excerpt' => 'Regions, redemption tips, and how Games Spot delivers codes.',
                    'body' => '<p>Always match the account region to the card. After checkout, credentials are delivered to your account when payment clears.</p>',
                ],
                'ar' => [
                    'title' => 'بطاقات الهدايا الرقمية: ما يجب معرفته قبل الشراء',
                    'excerpt' => 'المناطق ونصائح الاسترداد وكيف توصل Games Spot الأكواد.',
                    'body' => '<p>طابق دائماً منطقة الحساب مع البطاقة. بعد إتمام الدفع تُسلَّم بيانات الدخول إلى حسابك.</p>',
                ],
            ],

            // —— Tournaments ——
            [
                'type' => StorefrontCommunityPost::TYPE_TOURNAMENT,
                'slug' => self::SLUG_PREFIX.'tournament-fifa-open',
                'cover' => $img[1],
                'gallery' => [$img[0], $img[3], $img[5]],
                'starts_at' => now()->addDays(12)->setTime(16, 0),
                'ends_at' => now()->addDays(12)->setTime(22, 0),
                'published_at' => now()->subDay(),
                'location_id' => $locationId,
                'prize_pool' => 'EGP 10,000',
                'entry_fee' => 'EGP 100',
                'available_spots' => 32,
                'registration_mode' => StorefrontCommunityPost::REGISTRATION_INTERNAL,
                'registration_open' => true,
                'en' => [
                    'title' => 'FIFA Open Cup — Spring',
                    'excerpt' => '1v1 tournament with live brackets at the store.',
                    'body' => '<p>Bring your A-game. Check-in opens one hour before kickoff. Controllers provided; you may bring your own.</p>',
                    'game_title' => 'EA FC / FIFA',
                    'rules' => '<ul><li>Single elimination</li><li>Best of 1 until finals (Bo3)</li><li>No custom tactics packs</li></ul>',
                    'registration_details' => 'Register with your name and mobile. Staff will confirm your slot by SMS.',
                ],
                'ar' => [
                    'title' => 'كأس فيفا المفتوحة — الربيع',
                    'excerpt' => 'بطولة فردية مع جداول مباشرة في المتجر.',
                    'body' => '<p>أحضِر مستواك الأفضل. التسجيل يبدأ قبل ساعة من الانطلاق. نوفر أجهزة التحكم ويمكنك إحضار جهازك.</p>',
                    'game_title' => 'EA FC / FIFA',
                    'rules' => '<ul><li>إقصاء مباشر</li><li>مباراة واحدة حتى النهائي (أفضل من 3)</li><li>بدون حزم تكتيكات مخصصة</li></ul>',
                    'registration_details' => 'سجّل باسمك ورقم جوالك. سيؤكد الفريق مكانك عبر رسالة.',
                ],
            ],
            [
                'type' => StorefrontCommunityPost::TYPE_TOURNAMENT,
                'slug' => self::SLUG_PREFIX.'tournament-cod-external',
                'cover' => $img[4],
                'gallery' => [$img[2], $img[6]],
                'starts_at' => now()->addDays(21)->setTime(15, 0),
                'ends_at' => now()->addDays(21)->setTime(21, 0),
                'published_at' => now()->subDays(3),
                'location_id' => $locationId,
                'prize_pool' => 'EGP 5,000 + merch',
                'entry_fee' => 'Free',
                'available_spots' => 16,
                'registration_mode' => StorefrontCommunityPost::REGISTRATION_EXTERNAL,
                'registration_url' => 'https://pos.gamesspoteg.com/',
                'registration_open' => true,
                'en' => [
                    'title' => 'Call of Duty Squad Scrims',
                    'excerpt' => '4v4 squads — register on our partner form.',
                    'body' => '<p>Team captains register externally. Bring headsets and be ready for map veto.</p>',
                    'game_title' => 'Call of Duty',
                    'rules' => '<p>Standard CDL-style ruleset. Full brief emailed after signup.</p>',
                    'registration_details' => 'Use the external registration link to secure your squad.',
                ],
                'ar' => [
                    'title' => 'سكيرمات Call of Duty للفرق',
                    'excerpt' => 'فرق 4 ضد 4 — سجّل عبر نموذج الشريك.',
                    'body' => '<p>قائد الفريق يسجّل خارجياً. أحضر سماعة وكن جاهزاً لاختيار الخرائط.</p>',
                    'game_title' => 'Call of Duty',
                    'rules' => '<p>قواعد بأسلوب CDL. التفاصيل الكاملة تُرسل بعد التسجيل.</p>',
                    'registration_details' => 'استخدم رابط التسجيل الخارجي لتأكيد فريقك.',
                ],
            ],
            [
                'type' => StorefrontCommunityPost::TYPE_TOURNAMENT,
                'slug' => self::SLUG_PREFIX.'tournament-tekken-past',
                'cover' => $img[3],
                'gallery' => [$img[0], $img[1], $img[5]],
                'starts_at' => now()->subDays(20)->setTime(17, 0),
                'ends_at' => now()->subDays(20)->setTime(23, 0),
                'published_at' => now()->subDays(25),
                'location_id' => $locationId,
                'prize_pool' => 'EGP 3,000',
                'entry_fee' => 'EGP 50',
                'available_spots' => 0,
                'registration_mode' => StorefrontCommunityPost::REGISTRATION_OFF,
                'registration_open' => false,
                'winner' => 'PlayerOne (Cairo)',
                'en' => [
                    'title' => 'Tekken 8 Local — Winter Finals',
                    'excerpt' => 'Recap of our winter fighting-game finals.',
                    'body' => '<p>An incredible night of Footsies and comebacks. Congrats to the champion.</p>',
                    'game_title' => 'Tekken 8',
                    'rules' => '<p>Double elimination, FT2 / FT3 finals.</p>',
                    'results' => '<ol><li>PlayerOne</li><li>ShadowKick</li><li>NileFury</li></ol>',
                    'highlights' => '<p>Crowd favorite: the round 3 perfect KO in winners finals.</p>',
                ],
                'ar' => [
                    'title' => 'تيكن 8 المحلي — نهائي الشتاء',
                    'excerpt' => 'ملخص نهائي ألعاب القتال الشتوي.',
                    'body' => '<p>ليلة رائعة من المباريات والعروض. تهانينا للبطل.</p>',
                    'game_title' => 'Tekken 8',
                    'rules' => '<p>إقصاء مزدوج، حتى نقطتين / نهائي حتى 3.</p>',
                    'results' => '<ol><li>PlayerOne</li><li>ShadowKick</li><li>NileFury</li></ol>',
                    'highlights' => '<p>لحظة الجمهور: ضربة قاضية مثالية في نهائي الفائزين.</p>',
                ],
            ],

            // —— Events ——
            [
                'type' => StorefrontCommunityPost::TYPE_EVENT,
                'slug' => self::SLUG_PREFIX.'event-launch-night',
                'cover' => $img[5],
                'gallery' => [$img[6], $img[0], $img[2]],
                'starts_at' => now()->addDays(7)->setTime(18, 0),
                'ends_at' => now()->addDays(7)->setTime(22, 0),
                'published_at' => now()->subDays(1),
                'location_id' => $locationId,
                'registration_mode' => StorefrontCommunityPost::REGISTRATION_INTERNAL,
                'registration_open' => true,
                'en' => [
                    'title' => 'New Releases Launch Night',
                    'excerpt' => 'First look demos, snacks, and exclusive store deals.',
                    'body' => '<p>Drop by for hands-on demos of the latest titles and limited-time in-store offers.</p>',
                    'registration_details' => 'Free entry — reserve a spot so we can plan capacity.',
                ],
                'ar' => [
                    'title' => 'ليلة إطلاق الإصدارات الجديدة',
                    'excerpt' => 'عروض تجريبية ووجبات خفيفة وعروض حصرية داخل المتجر.',
                    'body' => '<p>مرّ للتجربة المباشرة لأحدث الألعاب وعروض لفترة محدودة داخل الفرع.</p>',
                    'registration_details' => 'الدخول مجاني — احجز مكانك لتنظيم الطاقة الاستيعابية.',
                ],
            ],
            [
                'type' => StorefrontCommunityPost::TYPE_EVENT,
                'slug' => self::SLUG_PREFIX.'event-creators-meetup',
                'cover' => $img[2],
                'gallery' => [$img[4], $img[1]],
                'starts_at' => now()->addDays(18)->setTime(17, 30),
                'ends_at' => now()->addDays(18)->setTime(20, 30),
                'published_at' => now()->subDays(4),
                'location_id' => $locationId,
                'registration_mode' => StorefrontCommunityPost::REGISTRATION_EXTERNAL,
                'registration_url' => 'https://pos.gamesspoteg.com/',
                'registration_open' => true,
                'en' => [
                    'title' => 'Creators & Streamers Meetup',
                    'excerpt' => 'Network with local creators — RSVP on the external form.',
                    'body' => '<p>Panels on growth, gear recommendations from our staff, and collab matchmaking.</p>',
                    'registration_details' => 'RSVP via the external link; walk-ins subject to capacity.',
                ],
                'ar' => [
                    'title' => 'لقاء صنّاع المحتوى والستريمرز',
                    'excerpt' => 'تواصل مع المبدعين المحليين — أكّد عبر النموذج الخارجي.',
                    'body' => '<p>جلسات عن النمو وتوصيات الأجهزة من فريقنا وتوفيق التعاونات.</p>',
                    'registration_details' => 'أكّد الحضور عبر الرابط الخارجي؛ الحضور بدون حجز حسب السعة.',
                ],
            ],
            [
                'type' => StorefrontCommunityPost::TYPE_EVENT,
                'slug' => self::SLUG_PREFIX.'event-anniversary-past',
                'cover' => $img[6],
                'gallery' => [$img[5], $img[3], $img[0]],
                'starts_at' => now()->subDays(40)->setTime(16, 0),
                'ends_at' => now()->subDays(40)->setTime(22, 0),
                'published_at' => now()->subDays(45),
                'location_id' => $locationId,
                'registration_mode' => StorefrontCommunityPost::REGISTRATION_OFF,
                'registration_open' => false,
                'en' => [
                    'title' => 'Games Spot Anniversary Party',
                    'excerpt' => 'Photos and highlights from our anniversary celebration.',
                    'body' => '<p>We celebrated another year with the community — thank you for showing up.</p>',
                    'highlights' => '<p>Cosplay corner, raffle winners, and a packed photo wall.</p>',
                    'recap' => '<p>Over 200 guests joined across the evening. Watch for next year’s date.</p>',
                ],
                'ar' => [
                    'title' => 'حفلة ذكرى Games Spot',
                    'excerpt' => 'صور وأبرز لحظات احتفال الذكرى.',
                    'body' => '<p>احتفلنا بعام آخر مع المجتمع — شكراً لحضوركم.</p>',
                    'highlights' => '<p>ركن الأزياء والجوائز وجدار صور مزدحم.</p>',
                    'recap' => '<p>أكثر من 200 ضيف خلال المساء. ترقّبوا موعد العام القادم.</p>',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function createPost(int $businessId, array $def): StorefrontCommunityPost
    {
        $post = StorefrontCommunityPost::create([
            'business_id' => $businessId,
            'type' => $def['type'],
            'slug' => $def['slug'],
            'status' => StorefrontCommunityPost::STATUS_PUBLISHED,
            'cover_path' => $def['cover'],
            'is_featured' => (bool) ($def['is_featured'] ?? false),
            'location_id' => $def['location_id'] ?? null,
            'prize_pool' => $def['prize_pool'] ?? null,
            'entry_fee' => $def['entry_fee'] ?? null,
            'available_spots' => $def['available_spots'] ?? null,
            'registration_mode' => $def['registration_mode'] ?? StorefrontCommunityPost::REGISTRATION_OFF,
            'registration_url' => $def['registration_url'] ?? null,
            'registration_open' => (bool) ($def['registration_open'] ?? false),
            'winner' => $def['winner'] ?? null,
            'starts_at' => $def['starts_at'] ?? null,
            'ends_at' => $def['ends_at'] ?? null,
            'published_at' => $def['published_at'] ?? now(),
        ]);

        foreach (['en', 'ar'] as $locale) {
            $t = $def[$locale] ?? [];
            StorefrontCommunityPostTranslation::create([
                'community_post_id' => $post->id,
                'locale' => $locale,
                'title' => $t['title'] ?? '',
                'excerpt' => $t['excerpt'] ?? null,
                'body' => $t['body'] ?? null,
                'game_title' => $t['game_title'] ?? null,
                'rules' => $t['rules'] ?? null,
                'results' => $t['results'] ?? null,
                'highlights' => $t['highlights'] ?? null,
                'recap' => $t['recap'] ?? null,
                'registration_details' => $t['registration_details'] ?? null,
            ]);
        }

        $sort = 0;
        foreach ($def['gallery'] ?? [] as $path) {
            StorefrontCommunityPostMedia::create([
                'community_post_id' => $post->id,
                'kind' => StorefrontCommunityPostMedia::KIND_IMAGE,
                'path' => $path,
                'caption' => null,
                'sort_order' => $sort++,
            ]);
        }

        return $post;
    }
}
