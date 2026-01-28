import { Head, Link } from '@inertiajs/react';
import { login, register } from '@/routes';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import '../../../css/landing.css';
import { 
  ArrowRight,
  Brain,
  Briefcase,
  Check,
  ChevronDown,
  FileSpreadsheet,
  Image as ImageIcon,
  Layers,
  RefreshCw,
  Upload,
  Users,
  Workflow,
  Zap,
} from 'lucide-react';
import { useState } from 'react';

export default function Home() {
  const [expandedFaq, setExpandedFaq] = useState<number | null>(0);
  const [billingCycle, setBillingCycle] = useState<'annual' | 'monthly'>('monthly');

  const keyFeatures = [
    {
      icon: Brain,
      title: 'Brand DNA',
      description: 'Automatically enforce brand colors, typography, and layout across all outputs.',
    },
    {
      icon: Workflow,
      title: 'Production System',
      description: 'Not templates or prompts—a repeatable workflow designed for high-volume output.',
    },
    {
      icon: Users,
      title: 'Agency-Ready',
      description: 'Manage multiple brands with consistent results. Social managers and designers collaborate seamlessly.',
    },
  ];

  const useCases = [
    {
      title: 'Multi-brand social calendars',
      description: 'Manage dozens of accounts with consistent visual identity',
    },
    {
      title: 'Weekly & monthly content batches',
      description: 'Generate all your content at once with approved brand styles',
    },
    {
      title: 'Campaign rollouts',
      description: 'Launch campaigns across channels with perfect visual consistency',
    },
    {
      title: 'Localization & variations',
      description: 'Create region-specific content while maintaining brand DNA',
    },
  ];

  const faqs = [
    {
      question: 'Is SnapDraft just another AI image tool?',
      answer: 'No. SnapDraft is a production system built around planning, consistency, and volume — not prompts. We focus on helping teams produce hundreds of on-brand visuals at scale.'
    },
    {
      question: 'Does this replace designers?',
      answer: 'No. It reduces repetitive production so designers can focus on creative work. Social managers generate batches, designers review and refine. Everyone stays in control.'
    },
    {
      question: 'How accurate is the branding?',
      answer: 'SnapDraft enforces structure (colors, typography, layout) about 80% of the time. Final refinement stays human—that\'s what our canvas editor is for.'
    },
    {
      question: 'Can I use it for different clients?',
      answer: 'Yes. SnapDraft is built for agencies managing multiple brands. Upload brand references for each client, and the system learns and applies their identity automatically.'
    },
    {
      question: 'Is it post-ready or draft-ready?',
      answer: 'Both — depending on your workflow. Some teams post directly. Others use the canvas editor for refinements. You decide the level of polish needed.'
    },
    {
      question: 'What is SnapDraft not good for?',
      answer: 'Hyper-artistic branding and pixel-perfect print design (for now). We\'re focused on production-volume formats like social media, marketing materials, and digital content.'
    },
  ];

  const [featureOne, featureTwo, featureThree] = keyFeatures;
  const FeatureOneIcon = featureOne.icon;
  const FeatureTwoIcon = featureTwo.icon;
  const FeatureThreeIcon = featureThree.icon;

  return (
    <div className="sd-motion relative min-h-dvh bg-background text-foreground">
      <Head title="SnapDraft - Turn content plans into weeks of on-brand social visuals" />

      {/* Navbar */}
      <header className="fixed inset-x-0 top-0 z-50">
        <nav aria-label="Primary" className="mx-auto max-w-7xl px-4 pt-4 sm:px-6 sm:pt-6">
          <div className="flex items-center justify-between rounded-full border border-white/10 bg-black/40 px-4 py-2 shadow-lg backdrop-blur-xl sm:px-5">
            <Link href="/" className="flex items-center gap-3">
              <img
                src="/SnapdraftLogoBlack.svg"
                alt="SnapDraft"
                className="h-7 w-auto brightness-0 invert"
                loading="eager"
              />
            </Link>

            <div className="hidden items-center gap-7 text-sm font-medium text-white/70 md:flex">
              <a href="#features" className="transition-colors hover:text-white">
                Features
              </a>
              <a href="#process" className="transition-colors hover:text-white">
                Process
              </a>
              <a href="#pricing" className="transition-colors hover:text-white">
                Pricing
              </a>
              <a href="#faq" className="transition-colors hover:text-white">
                FAQ
              </a>
            </div>

            <div className="flex items-center gap-3">
              <a href={login().url} className="hidden text-sm font-medium text-white/70 transition-colors hover:text-white sm:inline">
                Sign in
              </a>
              <Button
                asChild
                className="sd-btn-primary h-10 rounded-full px-5"
              >
                <Link href={register().url}>Start your trial</Link>
              </Button>
            </div>
          </div>
        </nav>
      </header>

      <main>
        {/* SECTION 1: HERO - Dark with orange accents */}
        <section className="sd-hero-dark relative overflow-hidden pb-20 pt-32 sm:pb-28 sm:pt-44">
          {/* Grid overlay */}
          <div className="sd-hero-grid" />
          
          <div className="relative z-10 mx-auto max-w-7xl px-6">
            {/* Centered Hero Content */}
            <div className="mx-auto max-w-4xl text-center">
              <div className="sd-fade-up mb-8 flex items-center justify-center gap-4">
                <div className="sd-accent-line w-12" />
                <span className="text-sm font-medium tracking-wide text-[#f7931e]">Production system for agencies</span>
                <div className="sd-accent-line w-12" />
              </div>

              <h1 className="sd-fade-up text-balance font-sans text-4xl font-extrabold leading-[1.1] tracking-tight text-white sm:text-5xl lg:text-6xl">
                Turn content plans into weeks of{' '}
                <span className="text-[#f7931e]">on-brand social visuals</span>
              </h1>

              <p className="sd-fade-up sd-delay-1 mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-white/60">
                SnapDraft is a production system for social media agencies. Generate high-volume, brand-consistent visuals without slowing designers down.
              </p>

              <div className="sd-fade-up sd-delay-2 mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row sm:gap-6">
                <Button asChild size="lg" className="sd-btn-primary h-14 gap-2 rounded-full px-10 text-base">
                  <Link href={register().url}>
                    Start your trial <ArrowRight className="h-4 w-4" />
                  </Link>
                </Button>
                <p className="text-sm text-white/50">7-day trial · $7 · Cancel anytime</p>
              </div>
            </div>

            {/* Product Demo Placeholder */}
            <div className="sd-fade-up sd-delay-3 mx-auto mt-16 max-w-5xl">
              <div className="sd-demo-placeholder aspect-video w-full text-lg">
                Product Demo
              </div>
            </div>
          </div>
        </section>

        {/* SECTION 2: FEATURES + VALUE (White background) */}
        <section id="features" className="bg-white py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <span className="text-sm font-medium tracking-wide text-[#f7931e]">Features</span>
              <h2 className="mt-4 text-balance text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl font-sans">
                Built for production — not one-off design.
              </h2>
              <p className="mt-4 text-lg text-gray-600">
                SnapDraft turns your content plan into batches of on-brand visuals. Not prompts. Not templates. A repeatable production workflow.
              </p>
            </div>

            <div className="mx-auto mt-14 grid max-w-6xl gap-6 md:grid-cols-12">
              {/* Wide feature tile (top row) */}
              <div className="group md:col-span-7">
                <div className="sd-card flex h-full flex-col justify-between">
                  <div>
                    <div className="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#f7931e]/10">
                      <FeatureOneIcon className="h-6 w-6 text-[#f7931e]" />
                    </div>
                    <h3 className="text-xl font-semibold text-gray-900">{featureOne.title}</h3>
                    <p className="mt-3 text-sm leading-relaxed text-gray-600">{featureOne.description}</p>
                  </div>
                  <div className="mt-8 overflow-hidden rounded-xl bg-gray-100 p-4">
                    <img
                      src="/images/landing/feature-1.png"
                      alt=""
                      className="h-auto w-full rounded-lg"
                      loading="lazy"
                      onError={(e) => {
                        e.currentTarget.onerror = null;
                        e.currentTarget.src = '/images/landing/spreadsheet-placeholder.svg';
                      }}
                    />
                  </div>
                </div>
              </div>

              {/* Tall feature tile (top right) */}
              <div className="group md:col-span-5">
                <div className="sd-card flex h-full flex-col justify-between">
                  <div>
                    <div className="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#f7931e]/10">
                      <FeatureTwoIcon className="h-6 w-6 text-[#f7931e]" />
                    </div>
                    <h3 className="text-xl font-semibold text-gray-900">{featureTwo.title}</h3>
                    <p className="mt-3 text-sm leading-relaxed text-gray-600">{featureTwo.description}</p>
                  </div>
                  <div className="mt-8 overflow-hidden rounded-xl bg-gray-100 p-4">
                    <img
                      src="/images/landing/feature-2.png"
                      alt=""
                      className="h-auto w-full rounded-lg"
                      loading="lazy"
                      onError={(e) => {
                        e.currentTarget.onerror = null;
                        e.currentTarget.src = '/images/landing/pain-2.svg';
                      }}
                    />
                  </div>
                </div>
              </div>

              {/* Bottom left tile */}
              <div className="group md:col-span-5">
                <div className="sd-card flex h-full flex-col justify-between">
                  <div>
                    <div className="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#f7931e]/10">
                      <FeatureThreeIcon className="h-6 w-6 text-[#f7931e]" />
                    </div>
                    <h3 className="text-xl font-semibold text-gray-900">{featureThree.title}</h3>
                    <p className="mt-3 text-sm leading-relaxed text-gray-600">{featureThree.description}</p>
                  </div>
                  <div className="mt-8 overflow-hidden rounded-xl bg-gray-100 p-4">
                    <img
                      src="/images/landing/feature-3.png"
                      alt=""
                      className="h-auto w-full rounded-lg"
                      loading="lazy"
                      onError={(e) => {
                        e.currentTarget.onerror = null;
                        e.currentTarget.src = '/images/landing/pain-3.svg';
                      }}
                    />
                  </div>
                </div>
              </div>

              {/* Bottom right tile (visual-only) */}
              <div className="group md:col-span-7">
                <div className="sd-card flex h-full flex-col justify-between">
                  <div>
                    <div className="text-sm font-medium text-gray-500">Production-ready outputs</div>
                    <div className="mt-2 h-2 w-16 rounded-full bg-[#f7931e]" />
                  </div>
                  <div className="mt-8 overflow-hidden rounded-xl bg-gray-100 p-4">
                    <img
                      src="/images/landing/feature-4.png"
                      alt=""
                      className="h-auto w-full rounded-lg"
                      loading="lazy"
                      onError={(e) => {
                        e.currentTarget.onerror = null;
                        e.currentTarget.src = '/images/landing/pain-4.svg';
                      }}
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className="mt-20 grid gap-10 lg:grid-cols-12 lg:items-start">
              <div className="lg:col-span-5">
                <h3 className="text-balance text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl font-sans">
                  Value without slowing designers down.
                </h3>
                <p className="mt-4 text-lg text-gray-600">
                  SnapDraft removes repetitive production work so teams can move faster while staying on-brand.
                </p>
              </div>
              <div className="lg:col-span-7">
                <div className="grid gap-4 sm:grid-cols-2">
                  {[
                    { title: 'Fewer briefs', icon: Briefcase },
                    { title: 'Less repetitive layout work', icon: Layers },
                    { title: 'Faster turnaround', icon: Zap },
                    { title: 'Better use of design time', icon: RefreshCw },
                  ].map((item, idx) => (
                    <div key={idx} className="sd-card flex items-center gap-4 !p-5">
                      <div className="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-[#f7931e]/10">
                        <item.icon className="h-5 w-5 text-[#f7931e]" />
                      </div>
                      <span className="font-semibold text-gray-900">{item.title}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="mt-20">
              <h3 className="text-center text-balance text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl font-sans">
                Built for real agency workflows.
              </h3>
              <div className="mx-auto mt-10 grid max-w-5xl gap-4 sm:grid-cols-2">
                {useCases.map((useCase, idx) => (
                  <div key={idx} className="sd-card">
                    <h4 className="text-lg font-semibold text-gray-900">{useCase.title}</h4>
                    <p className="mt-2 text-sm leading-relaxed text-gray-600">{useCase.description}</p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        {/* SECTION 3: PROCESS (Dark section with proper contrast) */}
        <section id="process" className="sd-section-dark py-24 sm:py-32">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <span className="text-sm font-medium tracking-wide text-[#f7931e]">How it works</span>
              <h2 className="mt-4 text-balance text-3xl font-bold tracking-tight text-white sm:text-4xl font-sans">
                From plan to visuals in three steps
              </h2>
              <p className="mt-4 text-lg text-white/60">
                A repeatable workflow that scales with your team.
              </p>
            </div>

            <div className="relative mx-auto mt-14 grid max-w-5xl gap-6 sm:grid-cols-3">
              {[
                {
                  step: 1,
                  title: 'Upload brand references',
                  description: 'Logos, past posts, brand visuals — SnapDraft learns the structure.',
                  icon: Upload,
                },
                {
                  step: 2,
                  title: 'Drop your content plan',
                  description: 'Use a spreadsheet with titles, captions, formats.',
                  icon: FileSpreadsheet,
                },
                {
                  step: 3,
                  title: 'Generate a batch',
                  description: 'Get consistent visuals — post-ready or ready for quick refinement.',
                  icon: ImageIcon,
                },
              ].map((item) => (
                <div key={item.step} className="relative">
                  <div className="sd-card-dark text-center">
                    <div className="mx-auto mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-[#f7931e] text-white font-bold">
                      {item.step}
                    </div>
                    <div className="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-xl bg-white/5">
                      <item.icon className="h-7 w-7 text-[#f7931e]" />
                    </div>
                    <h3 className="text-lg font-semibold text-white">{item.title}</h3>
                    <p className="mt-2 text-sm text-white/60">{item.description}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* SECTION 4: PRICING (Gray background) */}
        <section id="pricing" className="bg-gray-50 py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <span className="text-sm font-medium tracking-wide text-[#f7931e]">Pricing</span>
              <h2 className="mt-4 text-balance text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl font-sans">
                Simple plans for serious work
              </h2>
              <p className="mt-4 text-lg text-gray-600">
                Choose the plan that matches your production volume.
              </p>
            </div>

            <div className="mx-auto mt-10 inline-flex items-center justify-center rounded-full border border-gray-200 bg-white p-1 text-sm font-medium text-gray-600 shadow-sm">
              <button
                type="button"
                onClick={() => setBillingCycle('annual')}
                className={`rounded-full px-5 py-2 transition-colors ${
                  billingCycle === 'annual'
                    ? 'bg-gray-900 text-white shadow'
                    : 'hover:text-gray-900'
                }`}
              >
                Annually
              </button>
              <button
                type="button"
                onClick={() => setBillingCycle('monthly')}
                className={`rounded-full px-5 py-2 transition-colors ${
                  billingCycle === 'monthly'
                    ? 'bg-gray-900 text-white shadow'
                    : 'hover:text-gray-900'
                }`}
              >
                Monthly
              </button>
            </div>

            <div className="mx-auto mt-12 grid max-w-5xl gap-6 md:grid-cols-3">
              {/* Plan 1 */}
              <div className="sd-card">
                <div className="text-sm font-semibold text-gray-500">Starter</div>
                <div className="mt-2 text-3xl font-bold text-gray-900">$7</div>
                <div className="text-sm text-gray-500">7-day trial</div>
                <ul className="mt-6 space-y-3 text-sm text-gray-600">
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />Brand analysis + style guide</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />CSV batch generation</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />Canvas editor access</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />Standard support</li>
                </ul>
                <Button asChild variant="outline" className="mt-8 w-full rounded-full border-gray-300 text-gray-700 hover:bg-gray-50 hover:border-gray-400">
                  <Link href={register().url}>Start your trial</Link>
                </Button>
              </div>

              {/* Plan 2 (highlight) */}
              <div className="sd-card relative !border-2 !border-[#f7931e] !bg-[#f7931e]/5">
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                  <span className="rounded-full bg-[#f7931e] px-4 py-1 text-xs font-semibold text-white shadow-lg">Most Popular</span>
                </div>
                <div className="flex items-center justify-between">
                  <div className="text-sm font-semibold text-gray-900">Pro</div>
                  {billingCycle === 'annual' && (
                    <span className="rounded-full bg-green-500 px-3 py-1 text-xs font-semibold text-white">Save 20%</span>
                  )}
                </div>
                <div className="mt-2 text-3xl font-bold text-gray-900">
                  {billingCycle === 'annual' ? '$149/mo' : '$189/mo'}
                </div>
                <div className="text-sm text-gray-500">For high-volume agencies</div>
                <ul className="mt-6 space-y-3 text-sm text-gray-600">
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />Everything in Starter</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />Multi-brand workspaces</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />Priority queueing</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />Advanced exports</li>
                </ul>
                <Button asChild className="sd-btn-primary mt-8 w-full rounded-full">
                  <Link href={register().url}>Start your trial</Link>
                </Button>
              </div>

              {/* Plan 3 */}
              <div className="sd-card">
                <div className="text-sm font-semibold text-gray-500">Enterprise</div>
                <div className="mt-2 text-3xl font-bold text-gray-900">Flexible</div>
                <div className="text-sm text-gray-500">Custom workflows + SLAs</div>
                <ul className="mt-6 space-y-3 text-sm text-gray-600">
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />Everything in Pro</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />Custom onboarding</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />Dedicated support</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#f7931e]" />Security review</li>
                </ul>
                <Button asChild variant="outline" className="mt-8 w-full rounded-full border-gray-300 text-gray-700 hover:bg-gray-50 hover:border-gray-400">
                  <Link href={register().url}>Contact sales</Link>
                </Button>
              </div>
            </div>
          </div>
        </section>

        {/* SECTION 5: BLOG (White background) */}
        <section id="blog" className="bg-white py-16 sm:py-20">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <span className="text-sm font-medium tracking-wide text-[#f7931e]">Blog</span>
              <h2 className="mt-4 text-balance text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl font-sans">
                Light reading from the team
              </h2>
              <p className="mt-4 text-lg text-gray-600">
                A few recent posts to show how we think about production systems.
              </p>
            </div>
            <div className="mx-auto mt-10 grid max-w-5xl gap-4 md:grid-cols-3">
              {[
                {
                  title: 'Top 10 workflows for batch social design',
                  tag: 'TOOLS',
                  image: '/images/landing/blog-1.png',
                  fallback: '/images/landing/blog-1.svg',
                },
                {
                  title: 'A complete guide to production timelines',
                  tag: 'INSIGHT',
                  image: '/images/landing/blog-2.png',
                  fallback: '/images/landing/blog-2.svg',
                },
                {
                  title: 'How to keep brand consistency at scale',
                  tag: 'MANAGEMENT',
                  image: '/images/landing/blog-3.png',
                  fallback: '/images/landing/blog-3.svg',
                },
              ].map((post) => (
                <div key={post.title} className="sd-card !p-0 overflow-hidden">
                  <div className="overflow-hidden bg-gray-100">
                    <img
                      src={post.image}
                      alt=""
                      className="h-auto w-full"
                      loading="lazy"
                      onError={(e) => {
                        e.currentTarget.onerror = null;
                        e.currentTarget.src = post.fallback;
                      }}
                    />
                  </div>
                  <div className="flex items-center justify-between p-4">
                    <p className="text-sm font-semibold text-gray-900">{post.title}</p>
                    <span className="inline-flex items-center rounded-full bg-[#f7931e]/10 px-3 py-1 text-[10px] font-semibold text-[#f7931e]">
                      {post.tag}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>
        {/* SECTION 6: FAQ (Gray background) */}
        <section id="faq" className="bg-gray-50 py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <span className="text-sm font-medium tracking-wide text-[#f7931e]">FAQ</span>
              <h2 className="mt-4 text-balance text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl font-sans">Frequently asked questions</h2>
            </div>

            <div className="mx-auto mt-14 max-w-2xl space-y-4">
              {faqs.map((faq, idx) => {
                const isOpen = expandedFaq === idx;
                return (
                  <div key={idx} className="sd-card !p-0 overflow-hidden">
                    <button
                      type="button"
                      aria-expanded={isOpen}
                      aria-controls={`faq-panel-${idx}`}
                      onClick={() => setExpandedFaq(isOpen ? null : idx)}
                      className="flex w-full items-center justify-between gap-4 p-5 text-left transition-colors hover:bg-gray-50"
                    >
                      <span className="font-semibold text-gray-900">{faq.question}</span>
                      <ChevronDown
                        className={`h-5 w-5 shrink-0 text-[#f7931e] transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                      />
                    </button>
                    {isOpen && (
                      <div
                        id={`faq-panel-${idx}`}
                        className="border-t border-gray-100 bg-gray-50 px-5 pb-5 pt-4 text-sm text-gray-600"
                      >
                        {faq.answer}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
        </section>

      </main>

      {/* Footer */}
      <footer className="bg-gray-900">
        <div className="mx-auto max-w-7xl px-6 py-12">
          <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <div className="text-base font-semibold tracking-tight font-sans text-white">SnapDraft</div>
            <div className="flex items-center gap-6">
              <Link href={login().url} className="text-sm text-gray-400 transition-colors hover:text-white">
                Sign in
              </Link>
              <Link href={register().url} className="text-sm text-gray-400 transition-colors hover:text-white">
                Get started
              </Link>
            </div>
          </div>
          <div className="mt-8 border-t border-gray-800 pt-8 text-center">
            <p className="text-sm text-gray-500">© {new Date().getFullYear()} SnapDraft. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  );
}
