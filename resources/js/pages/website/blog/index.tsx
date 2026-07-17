import { Reveal } from '@/components/marketing/reveal';
import MarketingLayout from '@/layouts/marketing-layout';
import { Link } from '@inertiajs/react';

interface BlogPostSummary {
    slug: string;
    title: string;
    excerpt: string;
    cover: string | null;
    category?: string;
    date: string | null;
    date_formatted: string | null;
    reading_minutes: number;
}

export default function BlogIndexPage({
    posts,
}: {
    posts: BlogPostSummary[];
}) {
    return (
        <MarketingLayout
            title="Blog - SnapDraft"
            description="Workflow tips for social media managers, freelancers, and agencies. Brand consistency, batch calendars, and shipping visuals without the designer wait."
        >
            <div className="sd-page-hero">
                <Reveal>
                    <div className="sd-sec-eyebrow">From the blog</div>
                    <h1>Tips for people who publish weekly</h1>
                    <p>
                        Brand consistency, spreadsheet-to-visual workflows, and
                        how to close revision loops without waiting on a
                        designer.
                    </p>
                </Reveal>
            </div>

            <section className="sd-section" style={{ paddingTop: 0 }}>
                {posts.length === 0 ? (
                    <Reveal>
                        <p className="sd-pricing-note">
                            No posts yet - check back soon.
                        </p>
                    </Reveal>
                ) : (
                    <div className="sd-blog-grid sd-home-blog-grid">
                        {posts.map((post, i) => (
                            <Reveal key={post.slug} delay={i * 80}>
                                <Link
                                    href={`/blog/${post.slug}`}
                                    className="sd-blog-card sd-home-blog-card"
                                >
                                    {post.cover && (
                                        <div className="sd-blog-card-cover">
                                            <img
                                                src={post.cover}
                                                alt=""
                                                loading="lazy"
                                            />
                                        </div>
                                    )}
                                    <div className="sd-blog-card-body">
                                        <span className="sd-blog-card-tag">
                                            {post.category ?? 'News'}
                                        </span>
                                        <h3>{post.title}</h3>
                                        {post.date_formatted && (
                                            <div className="sd-blog-card-date">
                                                {post.date_formatted}
                                            </div>
                                        )}
                                    </div>
                                </Link>
                            </Reveal>
                        ))}
                    </div>
                )}
            </section>
        </MarketingLayout>
    );
}
