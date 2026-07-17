import { Reveal } from '@/components/marketing/reveal';
import MarketingLayout from '@/layouts/marketing-layout';
import { register } from '@/routes';
import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';

interface BlogPost {
    slug: string;
    title: string;
    excerpt: string;
    cover: string | null;
    date: string | null;
    date_formatted: string | null;
    reading_minutes: number;
    html: string;
}

export default function BlogShowPage({ post }: { post: BlogPost }) {
    return (
        <MarketingLayout
            title={`${post.title} - SnapDraft Blog`}
            description={post.excerpt}
            ogImage={post.cover ?? undefined}
        >
            <div className="sd-page-hero">
                <Reveal>
                    <h1>{post.title}</h1>
                    <p>
                        {post.date_formatted && `${post.date_formatted} · `}
                        {post.reading_minutes} min read
                    </p>
                </Reveal>
            </div>

            {post.cover && (
                <Reveal className="sd-article-cover">
                    <img src={post.cover} alt="" />
                </Reveal>
            )}

            <article className="sd-article">
                <Link href="/blog" className="sd-article-back">
                    <ArrowLeft size={14} />
                    All articles
                </Link>
                <div
                    className="sd-prose"
                    dangerouslySetInnerHTML={{ __html: post.html }}
                />
            </article>

            <section className="sd-cta">
                <Reveal>
                    <h2>
                        Put it into <em>practice.</em>
                    </h2>
                    <p>
                        Turn your next content calendar into a batch of
                        on-brand visuals.
                    </p>
                    <div className="sd-cta-row">
                        <Link href={register().url} className="sd-btn-hero">
                            Get started
                            <ArrowRight size={16} />
                        </Link>
                    </div>
                </Reveal>
            </section>
        </MarketingLayout>
    );
}
