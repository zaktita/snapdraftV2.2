import { Reveal } from '@/components/marketing/reveal';
import MarketingLayout from '@/layouts/marketing-layout';
import { useForm } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import { useState, type FormEvent } from 'react';

export default function ContactPage() {
    const [submitted, setSubmitted] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        message: '',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post('/contact', {
            preserveScroll: true,
            onSuccess: () => {
                setSubmitted(true);
                reset();
            },
        });
    }

    return (
        <MarketingLayout
            title="Contact - SnapDraft"
            description="Questions about SnapDraft for social managers, freelancers, or agencies? Pricing, workflows, or your account. Send a message."
        >
            <div className="sd-page-hero">
                <Reveal>
                    <div className="sd-sec-eyebrow">Contact</div>
                    <h1>
                        Talk to a <em>human</em>
                    </h1>
                    <p>
                        Questions about fitting SnapDraft into your client
                        workflow, pricing, or something else? Send a message.
                        It lands in the founder&apos;s inbox.
                    </p>
                </Reveal>
            </div>

            <div className="sd-contact-wrap">
                <Reveal>
                    <div className="sd-contact-card">
                        {submitted ? (
                            <div className="sd-contact-success">
                                <div className="sd-contact-success-icon">
                                    <Check size={28} />
                                </div>
                                <h3>Message sent</h3>
                                <p>
                                    Thanks for reaching out. We usually reply
                                    within one business day.
                                </p>
                            </div>
                        ) : (
                            <form
                                className="sd-contact-form"
                                onSubmit={handleSubmit}
                            >
                                <div className="sd-contact-field">
                                    <label htmlFor="contact-name">Name</label>
                                    <input
                                        id="contact-name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        placeholder="Your name"
                                        required
                                    />
                                    {errors.name && (
                                        <span className="sd-contact-error">
                                            {errors.name}
                                        </span>
                                    )}
                                </div>
                                <div className="sd-contact-field">
                                    <label htmlFor="contact-email">
                                        Email
                                    </label>
                                    <input
                                        id="contact-email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                        placeholder="you@company.com"
                                        required
                                    />
                                    {errors.email && (
                                        <span className="sd-contact-error">
                                            {errors.email}
                                        </span>
                                    )}
                                </div>
                                <div className="sd-contact-field">
                                    <label htmlFor="contact-message">
                                        Message
                                    </label>
                                    <textarea
                                        id="contact-message"
                                        value={data.message}
                                        onChange={(e) =>
                                            setData('message', e.target.value)
                                        }
                                        placeholder="What can we help with?"
                                        required
                                    />
                                    {errors.message && (
                                        <span className="sd-contact-error">
                                            {errors.message}
                                        </span>
                                    )}
                                </div>
                                <button
                                    type="submit"
                                    className="sd-btn-hero"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Sending…'
                                        : 'Send message'}
                                    <ArrowRight size={16} />
                                </button>
                            </form>
                        )}
                    </div>
                    <p className="sd-contact-aside">
                        Prefer email? Write to{' '}
                        <a href="mailto:contact@snapdraft.com">
                            contact@snapdraft.com
                        </a>
                    </p>
                </Reveal>
            </div>
        </MarketingLayout>
    );
}
