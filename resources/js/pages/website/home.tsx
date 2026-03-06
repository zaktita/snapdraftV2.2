import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowRight,
    Check,
    ChevronDown,
    ChevronUp,
    FileSpreadsheet,
    ImageIcon,
    Layers,
    Paintbrush,
    Sparkles,
    Upload,
    Zap,
} from 'lucide-react';
import { login, register } from '@/routes';

const FEATURES = [
    {
        icon: Paintbrush,
        title: 'Brand DNA Extraction',
        description:
            'Upload 5–10 reference images and our AI extracts your exact color palette, typography style, and composition rules automatically.',
    },
    {
        icon: FileSpreadsheet,
        title: 'CSV-Powered Batch Generation',
        description:
            'Provide a CSV with title, description, and format columns. One row = one visual. Generate up to 25 brand-consistent outputs in a single run.',
    },
    {
        icon: Layers,
        title: 'Canvas Editor',
        description:
            'Fine-tune any generated image with AI-powered erase, text replacement, and in-painting — directly in the browser.',
    },
    {
        icon: ImageIcon,
        title: 'Multiple Output Formats',
        description:
            'Generate social posts, banners, product cards, ads, and more — all matched to your brand style from a single batch.',
    },
];

const STEPS = [
    {
        number: '01',
        title: 'Upload Brand References',
        description:
            'Drag in 5–10 images that represent your brand — product photos, past ads, mood board images. Our AI studies them to extract your visual identity.',
        icon: Upload,
    },
    {
        number: '02',
        title: 'Upload Your CSV',
        description:
            'Prepare a simple CSV with three columns: title, description, and format. Each row becomes one generated visual.',
        icon: FileSpreadsheet,
    },
    {
        number: '03',
        title: 'Get Brand-Consistent Visuals',
        description:
            'SnapDraft generates every image in your brand style — colors, typography, layout — consistently across your entire batch.',
        icon: Sparkles,
    },
];

const FAQS = [
    {
        question: 'What file types can I use as brand references?',
        answer:
            'JPG, PNG, and WebP are all supported. We recommend using 5–10 high-quality images that best represent your brand style — product shots, past campaigns, or design system examples all work well.',
    },
    {
        question: 'How does the CSV format work?',
        answer:
            'Your CSV needs three columns: title (the name of the visual), description (what it should show), and format (e.g. "social post", "banner", "product card"). Download the template from the CSV Wizard for a ready-to-fill example.',
    },
    {
        question: 'How many images can I generate per batch?',
        answer:
            'The SnapDraft Beta plan supports up to 25 rows per CSV run and 100 credits per month. Each generated image costs 1 credit.',
    },
    {
        question: 'Can I edit the generated images?',
        answer:
            'Yes. Every generated image can be opened in the Canvas Editor where you can use AI-powered erase, replace text, and in-painting tools to fine-tune the result.',
    },
    {
        question: 'Is there a free trial?',
        answer:
            'Yes — the SnapDraft Beta plan includes a 7-day free trial. No credit card required to start. You\'ll be charged $29/month after the trial ends if you choose to continue.',
    },
];

export default function HomePage() {
    const [openFaq, setOpenFaq] = useState<number | null>(null);

    return (
        <>
            <Head title="SnapDraft — Brand-consistent visuals from your CSV" />

            {/* ── NAV ── */}
            <header className="sticky top-0 z-50 border-b border-slate-200 bg-white/95 backdrop-blur-sm">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <nav className="flex h-16 items-center justify-between">
                        <Link href="/" className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600">
                                <Zap className="h-4 w-4 text-white" />
                            </div>
                            <span className="text-lg font-bold text-slate-900">SnapDraft</span>
                        </Link>

                        <div className="hidden items-center gap-8 md:flex">
                            <a href="#features" className="text-sm font-medium text-slate-600 transition-colors hover:text-slate-900">
                                Features
                            </a>
                            <a href="#how-it-works" className="text-sm font-medium text-slate-600 transition-colors hover:text-slate-900">
                                How it works
                            </a>
                            <a href="#pricing" className="text-sm font-medium text-slate-600 transition-colors hover:text-slate-900">
                                Pricing
                            </a>
                            <a href="#faq" className="text-sm font-medium text-slate-600 transition-colors hover:text-slate-900">
                                FAQ
                            </a>
                        </div>

                        <div className="flex items-center gap-3">
                            <Link
                                href={login().url}
                                className="hidden text-sm font-medium text-slate-600 transition-colors hover:text-slate-900 sm:block"
                            >
                                Sign in
                            </Link>
                            <Link
                                href={register().url}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-indigo-700 hover:shadow-md hover:shadow-indigo-600/25"
                            >
                                Start free trial
                            </Link>
                        </div>
                    </nav>
                </div>
            </header>

            {/* ── HERO ── */}
            <section className="relative overflow-hidden bg-gradient-to-b from-indigo-50 via-white to-white py-20 lg:py-28">
                {/* background decoration */}
                <div className="pointer-events-none absolute inset-0 overflow-hidden">
                    <div className="absolute -top-40 right-0 h-[500px] w-[500px] rounded-full bg-indigo-100/60 blur-3xl" />
                    <div className="absolute bottom-0 left-0 h-[300px] w-[300px] rounded-full bg-violet-100/50 blur-3xl" />
                </div>

                <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-3xl text-center">
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-4 py-1.5 text-sm font-medium text-indigo-700">
                            <Sparkles className="h-3.5 w-3.5" />
                            Now in Beta — 7-day free trial
                        </div>

                        <h1 className="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                            Brand-consistent visuals{' '}
                            <span className="bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">
                                from your CSV
                            </span>
                            , in minutes
                        </h1>

                        <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-600">
                            Upload your brand reference images, drop in a CSV of your content, and SnapDraft generates a full batch of on-brand visuals — consistently styled, ready to use.
                        </p>

                        <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                            <Link
                                href={register().url}
                                className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition-all hover:bg-indigo-700 hover:shadow-indigo-600/40"
                            >
                                Start free trial
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                            <a
                                href="#how-it-works"
                                className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition-all hover:border-slate-300 hover:shadow-sm"
                            >
                                See how it works
                            </a>
                        </div>

                        <p className="mt-4 text-xs text-slate-500">
                            No credit card required · 7-day free trial · Cancel anytime
                        </p>
                    </div>

                    {/* Mock UI Card */}
                    <div className="mx-auto mt-16 max-w-4xl">
                        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/60">
                            {/* mock toolbar */}
                            <div className="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3">
                                <div className="h-3 w-3 rounded-full bg-red-400" />
                                <div className="h-3 w-3 rounded-full bg-yellow-400" />
                                <div className="h-3 w-3 rounded-full bg-green-400" />
                                <div className="mx-3 h-5 flex-1 rounded bg-slate-200" />
                            </div>
                            {/* mock content */}
                            <div className="grid grid-cols-3 gap-4 p-6 sm:grid-cols-4 lg:grid-cols-6">
                                {Array.from({ length: 12 }).map((_, i) => (
                                    <div
                                        key={i}
                                        className="aspect-square rounded-xl"
                                        style={{
                                            background: `hsl(${230 + (i % 4) * 15}, ${60 + (i % 3) * 10}%, ${85 + (i % 3) * 4}%)`,
                                        }}
                                    />
                                ))}
                            </div>
                            <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-6 py-3">
                                <span className="text-xs text-slate-500">12 visuals generated · brand style applied</span>
                                <span className="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Complete</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ── SOCIAL PROOF BAR ── */}
            <section className="border-y border-slate-100 bg-slate-50 py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p className="text-center text-sm font-medium text-slate-500">
                        Trusted by early adopters to generate brand-consistent content at scale
                    </p>
                    <div className="mt-6 grid grid-cols-2 gap-6 text-center sm:grid-cols-4">
                        {[
                            { value: '10x', label: 'Faster than manual design' },
                            { value: '25+', label: 'Visuals per CSV batch' },
                            { value: '100%', label: 'Brand style consistency' },
                            { value: '7-day', label: 'Free trial, no card needed' },
                        ].map((stat) => (
                            <div key={stat.label}>
                                <div className="text-2xl font-bold text-indigo-600">{stat.value}</div>
                                <div className="mt-1 text-xs text-slate-500">{stat.label}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── HOW IT WORKS ── */}
            <section id="how-it-works" className="py-20 lg:py-28">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-16 text-center">
                        <h2 className="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                            From CSV to visuals in 3 steps
                        </h2>
                        <p className="mx-auto mt-4 max-w-xl text-lg text-slate-600">
                            No design skills required. Just brand references and a spreadsheet.
                        </p>
                    </div>

                    <div className="grid gap-8 lg:grid-cols-3">
                        {STEPS.map((step, i) => (
                            <div key={i} className="relative rounded-2xl border border-slate-100 bg-white p-8 shadow-sm">
                                {/* connector line */}
                                {i < STEPS.length - 1 && (
                                    <div className="absolute -right-4 top-12 hidden h-0.5 w-8 bg-indigo-200 lg:block" />
                                )}
                                <div className="mb-4 flex items-center gap-3">
                                    <span className="text-4xl font-black text-indigo-100">{step.number}</span>
                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600">
                                        <step.icon className="h-5 w-5 text-white" />
                                    </div>
                                </div>
                                <h3 className="text-lg font-semibold text-slate-900">{step.title}</h3>
                                <p className="mt-2 text-sm leading-relaxed text-slate-600">{step.description}</p>
                            </div>
                        ))}
                    </div>

                    <div className="mt-12 text-center">
                        <Link
                            href={register().url}
                            className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white transition-all hover:bg-indigo-700"
                        >
                            Try it free
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </section>

            {/* ── FEATURES ── */}
            <section id="features" className="bg-slate-50 py-20 lg:py-28">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-16 text-center">
                        <h2 className="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                            Everything you need to scale visual content
                        </h2>
                        <p className="mx-auto mt-4 max-w-xl text-lg text-slate-600">
                            SnapDraft handles brand analysis, generation, and editing in one workflow.
                        </p>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        {FEATURES.map((feature, i) => (
                            <div
                                key={i}
                                className="rounded-2xl border border-slate-200 bg-white p-8 transition-all hover:border-indigo-200 hover:shadow-md"
                            >
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50">
                                    <feature.icon className="h-6 w-6 text-indigo-600" />
                                </div>
                                <h3 className="text-lg font-semibold text-slate-900">{feature.title}</h3>
                                <p className="mt-2 text-sm leading-relaxed text-slate-600">{feature.description}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── PRICING ── */}
            <section id="pricing" className="py-20 lg:py-28">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-12 text-center">
                        <h2 className="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                            Simple, transparent pricing
                        </h2>
                        <p className="mx-auto mt-4 max-w-xl text-lg text-slate-600">
                            One plan. Everything included. Start with a 7-day free trial.
                        </p>
                    </div>

                    <div className="mx-auto max-w-md">
                        <div className="relative overflow-hidden rounded-2xl border-2 border-indigo-600 bg-white shadow-xl shadow-indigo-600/10">
                            {/* popular badge */}
                            <div className="bg-indigo-600 py-2 text-center">
                                <span className="text-xs font-semibold uppercase tracking-wider text-white">
                                    SnapDraft Beta
                                </span>
                            </div>

                            <div className="p-8">
                                <div className="flex items-end gap-1">
                                    <span className="text-5xl font-bold text-slate-900">$29</span>
                                    <span className="mb-2 text-slate-500">/month</span>
                                </div>
                                <p className="mt-2 text-sm text-slate-500">after 7-day free trial</p>

                                <ul className="mt-8 space-y-3">
                                    {[
                                        '100 credits per month',
                                        'Up to 25 rows per CSV batch',
                                        'Up to 10 active projects',
                                        'Brand DNA analysis from reference images',
                                        'Canvas Editor (erase, replace text, AI edit)',
                                        'JPG, PNG, WebP reference support',
                                        'Bulk download of generated images',
                                        'Priority processing',
                                    ].map((item) => (
                                        <li key={item} className="flex items-start gap-3 text-sm text-slate-700">
                                            <Check className="mt-0.5 h-4 w-4 flex-shrink-0 text-indigo-600" />
                                            {item}
                                        </li>
                                    ))}
                                </ul>

                                <Link
                                    href={register().url}
                                    className="mt-8 block w-full rounded-xl bg-indigo-600 py-3 text-center text-sm font-semibold text-white transition-all hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-600/30"
                                >
                                    Start free trial — no card needed
                                </Link>

                                <p className="mt-3 text-center text-xs text-slate-400">Cancel anytime before trial ends</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ── FAQ ── */}
            <section id="faq" className="bg-slate-50 py-20 lg:py-28">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-12 text-center">
                        <h2 className="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                            Frequently asked questions
                        </h2>
                    </div>

                    <div className="mx-auto max-w-2xl space-y-3">
                        {FAQS.map((faq, i) => (
                            <div key={i} className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <button
                                    onClick={() => setOpenFaq(openFaq === i ? null : i)}
                                    className="flex w-full items-center justify-between px-6 py-5 text-left transition-colors hover:bg-slate-50"
                                >
                                    <span className="font-medium text-slate-900">{faq.question}</span>
                                    {openFaq === i ? (
                                        <ChevronUp className="h-4 w-4 flex-shrink-0 text-slate-400" />
                                    ) : (
                                        <ChevronDown className="h-4 w-4 flex-shrink-0 text-slate-400" />
                                    )}
                                </button>
                                {openFaq === i && (
                                    <div className="border-t border-slate-100 bg-slate-50 px-6 py-4">
                                        <p className="text-sm leading-relaxed text-slate-600">{faq.answer}</p>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── BOTTOM CTA ── */}
            <section className="bg-indigo-600 py-20">
                <div className="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                    <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                        Ready to generate your first batch?
                    </h2>
                    <p className="mx-auto mt-4 max-w-xl text-lg text-indigo-100">
                        Start your free 7-day trial today. No credit card required.
                    </p>
                    <div className="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Link
                            href={register().url}
                            className="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-semibold text-indigo-600 transition-all hover:bg-indigo-50 hover:shadow-lg"
                        >
                            Start free trial
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                        <Link
                            href={login().url}
                            className="text-sm font-medium text-indigo-100 transition-colors hover:text-white"
                        >
                            Already have an account? Sign in
                        </Link>
                    </div>
                </div>
            </section>

            {/* ── FOOTER ── */}
            <footer className="bg-slate-900 py-12 text-slate-400">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-8 sm:grid-cols-3">
                        <div>
                            <div className="flex items-center gap-2">
                                <div className="flex h-7 w-7 items-center justify-center rounded-md bg-indigo-600">
                                    <Zap className="h-3.5 w-3.5 text-white" />
                                </div>
                                <span className="font-bold text-white">SnapDraft</span>
                            </div>
                            <p className="mt-3 text-sm leading-relaxed">
                                AI-powered visual content generation for brands that need consistency at scale.
                            </p>
                        </div>

                        <div>
                            <h4 className="mb-3 text-sm font-semibold text-white">Product</h4>
                            <ul className="space-y-2 text-sm">
                                <li><a href="#features" className="transition-colors hover:text-white">Features</a></li>
                                <li><a href="#how-it-works" className="transition-colors hover:text-white">How it works</a></li>
                                <li><a href="#pricing" className="transition-colors hover:text-white">Pricing</a></li>
                                <li><a href="#faq" className="transition-colors hover:text-white">FAQ</a></li>
                            </ul>
                        </div>

                        <div>
                            <h4 className="mb-3 text-sm font-semibold text-white">Account</h4>
                            <ul className="space-y-2 text-sm">
                                <li>
                                    <Link href={register().url} className="transition-colors hover:text-white">
                                        Create account
                                    </Link>
                                </li>
                                <li>
                                    <Link href={login().url} className="transition-colors hover:text-white">
                                        Sign in
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div className="mt-10 border-t border-slate-800 pt-8 text-center text-xs">
                        © {new Date().getFullYear()} SnapDraft. All rights reserved.
                    </div>
                </div>
            </footer>
        </>
    );
}
