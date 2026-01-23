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

  return (
    <div className="sd-motion relative min-h-dvh bg-background text-foreground">
      <Head title="SnapDraft - Turn content plans into weeks of on-brand social visuals" />

      <div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        {/* Subtle grid like the reference hero */}
        <div className="absolute inset-0 bg-[linear-gradient(to_right,hsl(var(--border)/0.35)_1px,transparent_1px),linear-gradient(to_bottom,hsl(var(--border)/0.35)_1px,transparent_1px)] bg-[size:48px_48px] opacity-60 [mask-image:radial-gradient(60%_55%_at_50%_22%,black,transparent_70%)]" />
        {/* Gentle depth */}
        <div className="absolute inset-x-0 top-0 h-[28rem] bg-gradient-to-b from-background via-background/60 to-transparent" />
      </div>

      {/* Header */}
      <header className="fixed inset-x-0 top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <nav aria-label="Primary" className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6">
          <Link href="/" className="flex items-center gap-3">
            <img
              src="/SnapdraftLogoBlack.svg"
              alt="SnapDraft"
              className="h-7 w-auto dark:invert"
              loading="eager"
            />
          </Link>
          <div className="flex items-center gap-3">
            <Button asChild variant="ghost" size="sm">
              <Link href={login().url}>Sign in</Link>
            </Button>
            <Button asChild size="sm" className="gap-2">
              <Link href={register().url}>
                Start trial <ArrowRight className="h-4 w-4" />
              </Link>
            </Button>
          </div>
        </nav>
      </header>

      <main>
        {/* SECTION 1: HERO */}
        <section className="relative overflow-hidden pt-28 sm:pt-32">
          <div className="mx-auto max-w-7xl px-6">
            <div className="grid gap-14 lg:grid-cols-12 lg:items-center">
              <div className="lg:col-span-7">
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
              <div className="lg:col-span-5">
                <div className="sd-fade-up sd-delay-2 relative">
                  {/* Large hero image */}
                  <div className="relative ml-auto overflow-hidden rounded-2xl border border-border/60 bg-gradient-to-br from-background to-muted/30 shadow-2xl">
                    <img
                      src="/images/landing/generated-grid-placeholder.svg"
                      alt="Product visuals placeholder"
                      className="sd-float h-auto w-full"
                      loading="lazy"
                    />
                    <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(110deg,transparent,rgba(255,255,255,0.22),transparent)] opacity-0 transition-opacity duration-500 sd-shimmer group-hover:opacity-100" />
                  </div>

                  {/* Floating status card (no marketing claims) */}
                  <div className="sd-float absolute -left-4 top-10 w-56 rounded-xl border border-border/60 bg-background/80 p-4 shadow-xl backdrop-blur sm:-left-10">
                    <div className="mb-3 flex items-center justify-between">
                      <div className="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">System status</div>
                      <div className="h-2 w-2 rounded-full bg-emerald-500" />
                    </div>
                    <div className="space-y-3">
                      <div className="space-y-1">
                        <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                          <span>Consistency</span>
                          <span className="text-foreground/60">—</span>
                        </div>
                        <div className="h-1.5 rounded-full bg-muted/60">
                          <div className="h-full w-[78%] rounded-full bg-[var(--color-sidebar-primary)] opacity-80" />
                        </div>
                      </div>
                      <div className="space-y-1">
                        <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                          <span>Throughput</span>
                          <span className="text-foreground/60">—</span>
                        </div>
                        <div className="h-1.5 rounded-full bg-muted/60">
                          <div className="h-full w-[62%] rounded-full bg-foreground/30" />
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Accent metric block (decorative, no new copy) */}
                  <div className="sd-float absolute -left-2 bottom-6 w-64 rounded-2xl bg-[var(--color-sidebar-primary)] p-6 shadow-2xl [animation-delay:1.2s] sm:-left-8">
                    <div className="h-1.5 w-10 rounded-full bg-white/70" />
                    <div className="mt-6 space-y-3">
                      <div className="h-8 w-32 rounded bg-white/25" />
                      <div className="h-4 w-44 rounded bg-white/20" />
                      <div className="h-4 w-36 rounded bg-white/15" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* SECTION 2: PAIN SECTION */}
        <section className="mt-16 border-y border-border/50 bg-muted/20 py-20 sm:mt-24 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="grid gap-10 lg:grid-cols-12 lg:items-start">
              <div className="lg:col-span-5">
                <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                  Planning is easy. Production is the bottleneck.
                </h2>
                <p className="mt-4 text-lg text-muted-foreground">
                  Social teams don't need more ideas. They need a faster way to produce.
                </p>
              </div>

              <div className="lg:col-span-7">
                <div className="grid gap-4">
                  {painPoints.map((point, idx) => (
                    <div
                      key={idx}
                      className="flex items-start gap-4 rounded-xl border border-border/60 bg-background/60 p-5 shadow-sm backdrop-blur"
                    >
                      <div className="mt-0.5 grid h-9 w-9 place-items-center rounded-lg border border-border/60 bg-muted/30">
                        <AlertCircle className="h-4 w-4 text-destructive" />
                      </div>
                      <p className="text-muted-foreground">{point}</p>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* SECTION 3: SOLUTION */}
        <section className="py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                Built for production — not one-off design.
              </h2>
              <p className="mt-4 text-lg text-muted-foreground">
                SnapDraft turns your content plan into batches of on-brand visuals. Not prompts. Not templates. A repeatable production workflow.
              </p>
            </div>

            <div className="mx-auto mt-14 grid max-w-5xl gap-6 sm:grid-cols-3">
              {keyFeatures.map((feature, idx) => (
                <Card
                  key={idx}
                  className="group border-border/60 bg-background/70 shadow-sm transition-shadow hover:shadow-md"
                >
                  <CardHeader>
                    <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-border/60 bg-gradient-to-br from-muted/40 to-background">
                      <feature.icon className="h-6 w-6 text-foreground/80" />
                    </div>
                    <CardTitle className="text-xl">{feature.title}</CardTitle>
                    <CardDescription className="mt-2 text-sm leading-relaxed">{feature.description}</CardDescription>
                  </CardHeader>
                </Card>
              ))}
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
        <section className="py-20 sm:py-28">
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

        {/* SECTION 7: CANVAS EDITOR */}
        <section className="py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-6">
            <div className="mx-auto max-w-2xl text-center">
              <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl font-sans">
                Make final tweaks without restarting production.
              </h2>
              <p className="mt-4 text-lg text-muted-foreground">
                Adjust text, layout, or spacing directly in SnapDraft. No need to regenerate or jump between tools.
              </p>
            </div>

            <div className="mx-auto mt-14 max-w-5xl">
              <div className="group overflow-hidden rounded-2xl border border-border/60 bg-gradient-to-br from-background to-muted/30 shadow-xl transition-transform duration-300 hover:-translate-y-0.5">
                <div className="flex items-center justify-between border-b border-border/60 bg-background/50 px-4 py-3">
                  <div className="flex items-center gap-3">
                    <div className="grid h-9 w-9 place-items-center rounded-xl border border-border/60 bg-muted/30">
                      <Paintbrush className="h-4 w-4 text-foreground/80" />
                    </div>
                    <div className="text-sm font-medium text-muted-foreground">Canvas Editor Demo</div>
                  </div>
                  <div className="hidden items-center gap-2 sm:flex">
                    <div className="h-7 w-20 rounded-md border border-border/60 bg-background/40" />
                    <div className="h-7 w-20 rounded-md border border-border/60 bg-background/40" />
                  </div>
                </div>

                <div className="p-4 sm:p-6">
                  <div className="relative overflow-hidden rounded-xl border border-border/60 bg-background/40 shadow-sm">
                    <img
                      src="/images/landing/canvas-editor-placeholder.svg"
                      alt="Canvas editor interface placeholder"
                      className="h-auto w-full"
                      loading="lazy"
                    />
                    <div className="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                      <div className="absolute inset-0 bg-[radial-gradient(30rem_20rem_at_30%_10%,theme(colors.primary/12%),transparent)]" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* SECTION 8: DIFFERENTIATION */}
        <section className="border-y border-border/50 bg-muted/20 py-20 sm:py-28">
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
        <section className="py-20 sm:py-28">
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
