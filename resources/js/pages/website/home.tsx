import { Head, Link } from '@inertiajs/react';
import { login, register } from '@/routes';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import '../../../css/landing.css';
import { 
  AlertCircle,
  ArrowRight,
  Brain,
  Briefcase,
  Check,
  ChevronDown,
  Download,
  FileSpreadsheet,
  Image as ImageIcon,
  Layers,
  Paintbrush,
  RefreshCw,
  Sparkles,
  Upload,
  Users,
  Workflow,
  Zap,
} from 'lucide-react';
import { useState } from 'react';

export default function Home() {
  const [expandedFaq, setExpandedFaq] = useState<number | null>(0);
  const [billingCycle, setBillingCycle] = useState<'annual' | 'monthly'>('monthly');

  const painPoints = [
    'Content is approved — visuals aren\'t ready',
    'Designers are stuck on repetitive social formats',
    'AI tools create one-offs, not production-ready batches',
    'Brand consistency breaks at scale',
  ];

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

  const differentiation = [
    {
      icon: AlertCircle,
      title: 'Not Canva templates',
      description: 'We don\'t use templates—we use your brand DNA',
    },
    {
      icon: Sparkles,
      title: 'Not prompt-based AI tools',
      description: 'Structure and consistency, not guesswork and variation',
    },
    {
      icon: Download,
      title: 'Not one-off image generation',
      description: 'Built for volume, batches, and production workflows',
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

      <div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        {/* Subtle grid like the reference hero */}
        <div className="absolute inset-0 bg-[linear-gradient(to_right,hsl(var(--border)/0.35)_1px,transparent_1px),linear-gradient(to_bottom,hsl(var(--border)/0.35)_1px,transparent_1px)] bg-[size:48px_48px] opacity-60 [mask-image:radial-gradient(60%_55%_at_50%_22%,black,transparent_70%)]" />
        {/* Gentle depth */}
        <div className="absolute inset-x-0 top-0 h-[28rem] bg-gradient-to-b from-background via-background/60 to-transparent" />
      </div>

      {/* Navbar (reference-style pill) */}
      <header className="fixed inset-x-0 top-0 z-50">
        <nav aria-label="Primary" className="mx-auto max-w-7xl px-4 pt-4 sm:px-6 sm:pt-6">
          <div className="flex items-center justify-between rounded-full border border-border/60 bg-background/80 px-4 py-2 shadow-lg backdrop-blur supports-[backdrop-filter]:bg-background/60 sm:px-5">
            <Link href="/" className="flex items-center gap-3">
              <img
                src="/SnapdraftLogoBlack.svg"
                alt="SnapDraft"
                className="h-7 w-auto dark:invert"
                loading="eager"
              />
            </Link>

            <div className="hidden items-center gap-7 text-sm font-medium text-foreground/70 md:flex">
              <a href="#features" className="transition-colors hover:text-foreground">
                Features
              </a>
              <a href="#benefits" className="transition-colors hover:text-foreground">
                Benefits
              </a>
              <a href="#pricing" className="transition-colors hover:text-foreground">
                Pricing
              </a>
              <a href="/updates" className="transition-colors hover:text-foreground">
                Blog
              </a>
              <a href="#contact" className="transition-colors hover:text-foreground">
                Contact Us
              </a>
              <span className="h-1.5 w-1.5 rounded-full bg-[var(--color-sidebar-primary)]" />
            </div>

            <div className="flex items-center gap-3">
              <a href={login().url} className="hidden text-sm font-medium text-foreground/70 transition-colors hover:text-foreground sm:inline">
                Sign in
              </a>
              <Button
                asChild
                className="h-10 rounded-full bg-foreground px-5 text-background shadow-sm hover:bg-foreground/90"
              >
                <Link href={register().url}>Start your trial</Link>
              </Button>
            </div>
          </div>
        </nav>
      </header>

      <main>
        {/* SECTION 1: HERO */}
        <section className="relative overflow-hidden pt-32 sm:pt-40">
          <div className="mx-auto max-w-7xl px-6">
            <div className="grid gap-12 lg:grid-cols-12 lg:items-center">
              <div className="lg:col-span-6">
                {/* Accent micro-mark to echo reference without adding new copy */}
                <div className="sd-fade-up mb-6 flex items-center gap-3">
                  <div className="h-2 w-2 bg-[var(--color-sidebar-primary)]" />
                  <div className="h-px w-14 bg-[var(--color-sidebar-primary)] opacity-70" />
                </div>

                <h1 className="sd-fade-up text-balance font-sans text-5xl font-extrabold uppercase leading-[0.92] tracking-tight sm:text-7xl lg:text-7xl">
                  <span className="block text-foreground">Turn content plans</span>
                  <span className="block text-foreground/25">into weeks of on-brand</span>
                  <span className="block text-foreground">social visuals — in minutes.</span>
                </h1>

                <p className="sd-fade-up sd-delay-1 mt-6 max-w-xl text-lg leading-8 text-muted-foreground">
                  SnapDraft is a production system for social media agencies. Generate high-volume, brand-consistent visuals without slowing designers down.
                </p>

                <div className="sd-fade-up sd-delay-2 mt-10 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                  <Button asChild size="lg" className="h-12 gap-2 px-8 text-base shadow-sm">
                    <Link href={register().url}>
                      Start your trial <ArrowRight className="h-4 w-4" />
                    </Link>
                  </Button>
                  <p className="text-sm text-muted-foreground">7-day trial · $7 · Cancel anytime</p>
                </div>
              </div>

              {/* Hero Visual */}
              <div className="lg:col-span-6">
                <div className="sd-fade-up sd-delay-2 relative">
                  {/* Large hero image (clean: no border/shadow) */}
                  <div className="relative -mr-6 overflow-hidden rounded-2xl sm:-mr-10 lg:-mr-16">
                    <img
                      src="/images/landing/hero-reference.png"
                      alt="SnapDraft hero illustration"
                      className="h-auto w-full"
                      decoding="async"
                      loading="lazy"
                      onError={(e) => {
                        e.currentTarget.onerror = null;
                        e.currentTarget.src = '/images/landing/generated-grid-placeholder.svg';
                      }}
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* SECTION 2: PAIN SECTION */}
        <section className="mt-16 border-y border-border/50 bg-muted/20 py-20 sm:mt-24 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="grid gap-12 lg:grid-cols-12 lg:items-start">
              <div className="lg:col-span-5">
                <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                  Planning is easy. Production is the bottleneck.
                </h2>
                <p className="mt-4 text-lg text-muted-foreground">
                  Social teams don't need more ideas. They need a faster way to produce.
                </p>
              </div>

              <div className="lg:col-span-7">
                <div className="grid gap-4 sm:grid-cols-2">
                  {painPoints.map((point, idx) => (
                    <div
                      key={idx}
                      className="group flex flex-col gap-4 rounded-2xl border border-border/60 bg-background/70 p-5 shadow-sm transition-transform duration-200 hover:-translate-y-0.5"
                    >
                      <div className="flex items-start justify-between gap-4">
                        <img
                          src={`/images/landing/pain-${idx + 1}.svg`}
                          alt=""
                          className="h-20 w-28 rounded-xl bg-white"
                          loading="lazy"
                        />
                        <div className="mt-1 grid h-9 w-9 place-items-center rounded-full border border-border/60 bg-muted/30">
                          <AlertCircle className="h-4 w-4 text-destructive" />
                        </div>
                      </div>
                      <p className="text-sm text-muted-foreground">{point}</p>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* SECTION 3: SOLUTION */}
        <section id="features" className="py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                Built for production — not one-off design.
              </h2>
              <p className="mt-4 text-lg text-muted-foreground">
                SnapDraft turns your content plan into batches of on-brand visuals. Not prompts. Not templates. A repeatable production workflow.
              </p>
            </div>

            <div className="mx-auto mt-14 grid max-w-6xl gap-4 md:grid-cols-12">
              {/* Wide feature tile (top row) */}
              <div className="group md:col-span-7">
                <div className="flex h-full flex-col justify-between rounded-3xl border border-border/60 bg-background/70 p-8 shadow-sm transition-transform duration-200 hover:-translate-y-0.5">
                  <div>
                    <div className="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-border/60 bg-gradient-to-br from-muted/40 to-background">
                      <FeatureOneIcon className="h-6 w-6 text-foreground/80" />
                    </div>
                    <h3 className="text-xl font-semibold text-foreground">{featureOne.title}</h3>
                    <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{featureOne.description}</p>
                  </div>
                  <div className="mt-8 overflow-hidden rounded-2xl border border-border/60 bg-muted/20 p-4">
                    <img
                      src="/images/landing/feature-1.png"
                      alt=""
                      className="h-auto w-full"
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
                <div className="flex h-full flex-col justify-between rounded-3xl border border-border/60 bg-background/70 p-8 shadow-sm transition-transform duration-200 hover:-translate-y-0.5">
                  <div>
                    <div className="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-border/60 bg-gradient-to-br from-muted/40 to-background">
                      <FeatureTwoIcon className="h-6 w-6 text-foreground/80" />
                    </div>
                    <h3 className="text-xl font-semibold text-foreground">{featureTwo.title}</h3>
                    <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{featureTwo.description}</p>
                  </div>
                  <div className="mt-8 overflow-hidden rounded-2xl border border-border/60 bg-muted/20 p-4">
                    <img
                      src="/images/landing/feature-2.png"
                      alt=""
                      className="h-auto w-full"
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
                <div className="flex h-full flex-col justify-between rounded-3xl border border-border/60 bg-background/70 p-8 shadow-sm transition-transform duration-200 hover:-translate-y-0.5">
                  <div>
                    <div className="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-border/60 bg-gradient-to-br from-muted/40 to-background">
                      <FeatureThreeIcon className="h-6 w-6 text-foreground/80" />
                    </div>
                    <h3 className="text-xl font-semibold text-foreground">{featureThree.title}</h3>
                    <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{featureThree.description}</p>
                  </div>
                  <div className="mt-8 overflow-hidden rounded-2xl border border-border/60 bg-muted/20 p-4">
                    <img
                      src="/images/landing/feature-3.png"
                      alt=""
                      className="h-auto w-full"
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
                <div className="flex h-full flex-col justify-between rounded-3xl border border-border/60 bg-background/70 p-8 shadow-sm transition-transform duration-200 hover:-translate-y-0.5">
                  <div>
                    <div className="text-sm font-medium text-muted-foreground">Production-ready outputs</div>
                    <div className="mt-2 h-2 w-16 rounded-full bg-[var(--color-sidebar-primary)] opacity-70" />
                  </div>
                  <div className="mt-8 overflow-hidden rounded-2xl border border-border/60 bg-muted/20 p-4">
                    <img
                      src="/images/landing/feature-4.png"
                      alt=""
                      className="h-auto w-full"
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
          </div>
        </section>

        {/* SECTION 4: HOW IT WORKS (3 STEPS) */}
        <section className="border-y border-border/50 bg-muted/20 py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                From plan to visuals in three steps.
              </h2>
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
                  <div className="rounded-2xl border border-border/60 bg-background/70 p-8 text-center shadow-sm">
                    <div className="mx-auto mb-4 flex h-9 w-9 items-center justify-center rounded-full border border-border/60 bg-background shadow-sm">
                      <span className="text-sm font-semibold text-foreground">{item.step}</span>
                    </div>
                    <div className="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-2xl border border-border/60 bg-gradient-to-br from-muted/40 to-background">
                      <item.icon className="h-6 w-6 text-foreground/80" />
                    </div>
                    <h3 className="text-lg font-semibold">{item.title}</h3>
                    <p className="mt-2 text-sm text-muted-foreground">{item.description}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* SECTION 5: DESIGNER-FRIENDLY */}
        <section id="benefits" className="py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="grid gap-10 lg:grid-cols-12 lg:items-start">
              <div className="lg:col-span-5">
                <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                  Not a designer replacement. A production accelerator.
                </h2>
                <p className="mt-4 text-lg text-muted-foreground">
                  SnapDraft handles repetitive social production so designers can focus on creative work. Social managers can generate visuals themselves — designers stay in control of the final output.
                </p>
              </div>

              <div className="lg:col-span-7">
                <div className="grid gap-4 sm:grid-cols-2">
                  {[
                    { title: 'Fewer briefs', icon: Briefcase },
                    { title: 'Fewer repetitive layouts', icon: Layers },
                    { title: 'Faster turnaround', icon: Zap },
                    { title: 'Better use of design time', icon: RefreshCw },
                  ].map((item, idx) => (
                    <Card key={idx} className="border-border/60 bg-background/70 shadow-sm">
                      <CardHeader>
                        <div className="flex items-center gap-3">
                          <div className="grid h-10 w-10 place-items-center rounded-xl border border-border/60 bg-muted/30">
                            <item.icon className="h-5 w-5 text-foreground/80" />
                          </div>
                          <CardTitle className="text-base">{item.title}</CardTitle>
                        </div>
                      </CardHeader>
                    </Card>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* SECTION 6: USE CASES */}
        <section className="border-y border-border/50 bg-muted/20 py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                Built for real agency workflows.
              </h2>
            </div>

            <div className="mx-auto mt-14 grid max-w-5xl gap-4 sm:grid-cols-2">
              {useCases.map((useCase, idx) => (
                <Card
                  key={idx}
                  className="border-border/60 bg-background/70 shadow-sm transition-colors hover:border-foreground/20"
                >
                  <CardHeader>
                    <CardTitle className="text-lg">{useCase.title}</CardTitle>
                    <CardDescription className="text-sm leading-relaxed">{useCase.description}</CardDescription>
                  </CardHeader>
                </Card>
              ))}
            </div>

            <div className="mt-12 text-center">
              <p className="text-lg font-semibold text-foreground">Same workflow. Different clients. Consistent results.</p>
            </div>
          </div>
        </section>

        {/* SECTION 7: PRICING */}
        <section id="pricing" className="bg-muted/20 py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <p className="text-xs font-semibold uppercase tracking-[0.3em] text-muted-foreground">Pricing</p>
              <h2 className="mt-4 text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                Simple plans for serious work
              </h2>
              <p className="mt-4 text-lg text-muted-foreground">
                Choose the plan that matches your production volume.
              </p>
            </div>

            <div className="mx-auto mt-10 inline-flex items-center justify-center rounded-full border border-border/60 bg-background/80 p-1 text-xs font-medium text-muted-foreground shadow-sm">
              <button
                type="button"
                onClick={() => setBillingCycle('annual')}
                className={`rounded-full px-4 py-1.5 transition-colors ${
                  billingCycle === 'annual'
                    ? 'bg-background text-foreground shadow'
                    : 'hover:text-foreground'
                }`}
              >
                Annually
              </button>
              <button
                type="button"
                onClick={() => setBillingCycle('monthly')}
                className={`rounded-full px-4 py-1.5 transition-colors ${
                  billingCycle === 'monthly'
                    ? 'bg-background text-foreground shadow'
                    : 'hover:text-foreground'
                }`}
              >
                Monthly
              </button>
            </div>

            <div className="mx-auto mt-12 grid max-w-5xl gap-6 md:grid-cols-3">
              {/* Plan 1 */}
              <div className="rounded-3xl border border-border/60 bg-background/90 p-8 shadow-sm">
                <div className="text-sm font-semibold text-muted-foreground">Starter</div>
                <div className="mt-2 text-3xl font-bold text-foreground">$7</div>
                <div className="text-sm text-muted-foreground">7-day trial</div>
                <ul className="mt-6 space-y-3 text-sm text-muted-foreground">
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />Brand analysis + style guide</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />CSV batch generation</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />Canvas editor access</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />Standard support</li>
                </ul>
                <Button asChild variant="ghost" className="mt-8 w-full rounded-full">
                  <Link href={register().url}>Start your trial</Link>
                </Button>
              </div>

              {/* Plan 2 (highlight) */}
              <div className="rounded-3xl border-2 border-[var(--color-sidebar-primary)] bg-[var(--color-sidebar-primary)]/10 p-8 shadow-sm">
                <div className="flex items-center justify-between">
                  <div className="text-sm font-semibold text-foreground">Pro</div>
                  {billingCycle === 'annual' && (
                    <span className="rounded-full bg-[var(--color-sidebar-primary)] px-3 py-1 text-xs font-semibold text-white">Save 20%</span>
                  )}
                </div>
                <div className="mt-2 text-3xl font-bold text-foreground">
                  {billingCycle === 'annual' ? '$149/mo' : '$189/mo'}
                </div>
                <div className="text-sm text-muted-foreground">For high-volume agencies</div>
                <ul className="mt-6 space-y-3 text-sm text-muted-foreground">
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />Everything in Starter</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />Multi-brand workspaces</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />Priority queueing</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />Advanced exports</li>
                </ul>
                <Button asChild className="mt-8 w-full rounded-full bg-foreground text-background hover:bg-foreground/90">
                  <Link href={register().url}>Start your trial</Link>
                </Button>
              </div>

              {/* Plan 3 */}
              <div className="rounded-3xl border border-border/60 bg-background/90 p-8 shadow-sm">
                <div className="text-sm font-semibold text-muted-foreground">Enterprise</div>
                <div className="mt-2 text-3xl font-bold text-foreground">Flexible</div>
                <div className="text-sm text-muted-foreground">Custom workflows + SLAs</div>
                <ul className="mt-6 space-y-3 text-sm text-muted-foreground">
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />Everything in Pro</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />Custom onboarding</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />Dedicated support</li>
                  <li className="flex items-center gap-2"><Check className="h-4 w-4 text-foreground/60" />Security review</li>
                </ul>
                <Button asChild variant="ghost" className="mt-8 w-full rounded-full">
                  <Link href={register().url}>Contact sales</Link>
                </Button>
              </div>
            </div>
          </div>
        </section>

        {/* SECTION 8: BLOG */}
        <section id="blog" className="bg-[#EAF2FB] py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <p className="text-xs font-semibold uppercase tracking-[0.3em] text-muted-foreground">Blog</p>
              <h2 className="mt-4 text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                Ideas to level-up your creative output
              </h2>
            </div>

            <div className="mx-auto mt-12 grid max-w-5xl gap-6 rounded-3xl bg-white p-6 shadow-sm md:grid-cols-[1.2fr_1fr] md:items-center">
              <div className="overflow-hidden rounded-2xl bg-[#F7F4EF]">
                <img
                  src="/images/landing/blog-featured.png"
                  alt="Featured article"
                  className="h-auto w-full"
                  loading="lazy"
                  onError={(e) => {
                    e.currentTarget.onerror = null;
                    e.currentTarget.src = '/images/landing/blog-featured.svg';
                  }}
                />
              </div>
              <div className="space-y-4">
                <span className="inline-flex items-center rounded-full bg-[var(--color-sidebar-primary)]/10 px-3 py-1 text-xs font-semibold text-[var(--color-sidebar-primary)]">
                  MUST READ
                </span>
                <h3 className="text-2xl font-semibold text-foreground">
                  How to build a reliable content production system in 2025
                </h3>
                <p className="text-sm text-muted-foreground">
                  Learn how to ship high-volume social visuals with repeatable workflows and consistent brand output.
                </p>
                <div className="flex items-center justify-between pt-4">
                  <div className="flex items-center gap-3">
                    <img
                      src="/images/landing/avatar-1.svg"
                      alt=""
                      className="h-10 w-10 rounded-full"
                      loading="lazy"
                    />
                    <div className="text-xs text-muted-foreground">
                      <div className="font-semibold text-foreground">SnapDraft Team</div>
                      <div>Editorial</div>
                    </div>
                  </div>
                  <span className="inline-flex items-center rounded-full bg-[var(--color-sidebar-primary)] px-3 py-1 text-xs font-semibold text-white">
                    FEATURED
                  </span>
                </div>
              </div>
            </div>

            <div className="mx-auto mt-8 grid max-w-5xl gap-6 md:grid-cols-3">
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
                <div key={post.title} className="space-y-4">
                  <div className="overflow-hidden rounded-2xl bg-white shadow-sm">
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
                  <div className="flex items-center justify-between">
                    <p className="text-sm font-semibold text-foreground">{post.title}</p>
                    <span className="inline-flex items-center rounded-full bg-[var(--color-sidebar-primary)]/15 px-3 py-1 text-[10px] font-semibold text-[var(--color-sidebar-primary)]">
                      {post.tag}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* SECTION 8: DIFFERENTIATION */}
        <section id="faq" className="border-y border-border/50 bg-muted/20 py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                Why agencies choose SnapDraft.
              </h2>
            </div>

            <div className="mx-auto mt-14 grid max-w-5xl gap-6 sm:grid-cols-3">
              {differentiation.map((item, idx) => (
                <Card key={idx} className="border-border/60 bg-background/70 text-center shadow-sm">
                  <CardHeader>
                    <div className="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-2xl border border-border/60 bg-muted/30">
                      <item.icon className="h-6 w-6 text-muted-foreground line-through" />
                    </div>
                    <CardTitle className="text-lg">{item.title}</CardTitle>
                    <CardDescription className="mt-2 text-sm leading-relaxed">{item.description}</CardDescription>
                  </CardHeader>
                </Card>
              ))}
            </div>

            <div className="mt-12 text-center">
              <p className="text-lg font-semibold text-foreground">SnapDraft is built for volume, consistency, and speed.</p>
            </div>
          </div>
        </section>

        {/* SECTION 9: PRICING / TRIAL CTA */}
        <section id="contact" className="py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                Try SnapDraft with real production.
              </h2>
              <p className="mt-4 text-lg text-muted-foreground">
                A serious tool for serious workflows. No free toy. No long-term commitment.
              </p>
            </div>

            <div className="mx-auto mt-14 max-w-md text-center">
              <Button asChild size="lg" className="h-12 w-full gap-2 px-8 text-base">
                <Link href={register().url}>
                  Start your trial <ArrowRight className="h-4 w-4" />
                </Link>
              </Button>
              <p className="mt-4 text-sm text-muted-foreground">7-day trial · $7 · Cancel anytime</p>
            </div>
          </div>
        </section>

        {/* SECTION 10: FAQ */}
        <section className="border-y border-border/50 bg-muted/20 py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">Frequently asked questions</h2>
            </div>

            <div className="mx-auto mt-14 max-w-2xl space-y-3">
              {faqs.map((faq, idx) => {
                const isOpen = expandedFaq === idx;
                return (
                  <div key={idx} className="overflow-hidden rounded-xl border border-border/60 bg-background/70 shadow-sm">
                    <button
                      type="button"
                      aria-expanded={isOpen}
                      aria-controls={`faq-panel-${idx}`}
                      onClick={() => setExpandedFaq(isOpen ? null : idx)}
                      className="flex w-full items-center justify-between gap-4 p-5 text-left transition-colors hover:bg-muted/30"
                    >
                      <span className="font-semibold text-foreground">{faq.question}</span>
                      <ChevronDown
                        className={`h-5 w-5 shrink-0 text-muted-foreground transition-transform ${isOpen ? 'rotate-180' : ''}`}
                      />
                    </button>
                    {isOpen && (
                      <div
                        id={`faq-panel-${idx}`}
                        className="border-t border-border/60 px-5 pb-5 pt-4 text-sm text-muted-foreground"
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

        {/* SECTION 11: FINAL CTA */}
        <section className="py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="relative overflow-hidden rounded-3xl border border-border/20 bg-gradient-to-br from-primary/95 to-primary px-8 py-16 text-center shadow-2xl sm:px-16 sm:py-24">
              <div className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(45rem_50rem_at_top,theme(colors.background/12%),transparent)]" />

              <h2 className="text-balance text-3xl font-bold tracking-tight text-primary-foreground sm:text-4xl font-sans">
                Stop waiting on production.
              </h2>
              <p className="mx-auto mt-4 max-w-xl text-lg text-primary-foreground/90">
                Join agencies creating thousands of brand-consistent visuals every week.
              </p>
              <div className="mt-8">
                <Button asChild size="lg" variant="secondary" className="h-12 gap-2 px-8 text-base">
                  <Link href={register().url}>
                    Start your trial <ArrowRight className="h-4 w-4" />
                  </Link>
                </Button>
                <p className="mt-4 text-sm text-primary-foreground/90">7-day trial · $7 · Cancel anytime</p>
              </div>
            </div>
          </div>
        </section>
      </main>

      {/* Footer */}
      <footer className="border-t border-border/50">
        <div className="mx-auto max-w-7xl px-6 py-12">
          <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <div className="text-base font-semibold tracking-tight font-sans text-muted-foreground">SnapDraft</div>
            <div className="flex items-center gap-6">
              <Link href={login().url} className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                Sign in
              </Link>
              <Link href={register().url} className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                Get started
              </Link>
            </div>
          </div>
          <div className="mt-8 border-t border-border/50 pt-8 text-center">
            <p className="text-sm text-muted-foreground">© {new Date().getFullYear()} SnapDraft. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  );
}
