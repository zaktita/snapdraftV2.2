import { Head, Link } from '@inertiajs/react';
import { login, register } from '@/routes';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { 
  Sparkles, Zap, Paintbrush, ArrowRight, Upload, FileSpreadsheet, Image as ImageIcon, Download, Check, 
  TrendingUp, Users, Clock, AlertCircle, Layers, Palette, BarChart3, RefreshCw, Shield, Compass, 
  ChevronDown, Brain, Briefcase, Workflow, GripHorizontal
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
    <div className="min-h-dvh bg-background">
      <Head title="SnapDraft - Turn content plans into weeks of on-brand social visuals" />

      {/* Header */}
      <header className="fixed top-0 z-50 w-full border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <nav className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
          <Link href="/" className="flex items-center gap-2">
            <div className="text-2xl font-bold tracking-tight font-sans">SnapDraft</div>
          </Link>
          <div className="flex items-center gap-4">
            <Link href={login().url}>
              <Button variant="ghost" size="sm">Sign in</Button>
            </Link>
            <Link href={register().url}>
              <Button size="sm" className="gap-2">
                Start trial <ArrowRight className="h-4 w-4" />
              </Button>
            </Link>
          </div>
        </nav>
      </header>

      {/* SECTION 1: HERO */}
      <section className="relative overflow-hidden pt-32 pb-24 sm:pt-40 sm:pb-32">
        <div className="absolute inset-0 -z-10 bg-[radial-gradient(45rem_50rem_at_top,theme(colors.primary.200/20%),transparent)] dark:bg-[radial-gradient(45rem_50rem_at_top,theme(colors.primary.900/20%),transparent)]" />
        
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-3xl text-center">
            <h1 className="text-5xl font-bold tracking-tight sm:text-7xl font-sans bg-gradient-to-br from-foreground to-foreground/60 bg-clip-text text-transparent">
              Turn content plans into weeks of on-brand social visuals — in minutes.
            </h1>
            
            <p className="mt-6 text-lg leading-8 text-muted-foreground max-w-2xl mx-auto">
              SnapDraft is a production system for social media agencies. Generate high-volume, brand-consistent visuals without slowing designers down.
            </p>
            
            <div className="mt-10 flex flex-col items-center gap-4">
              <Link href={register().url}>
                <Button size="lg" className="gap-2 text-base h-12 px-8">
                  Start your trial <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
              <p className="text-sm text-muted-foreground">
                7-day trial · $7 · Cancel anytime
              </p>
            </div>
          </div>

          {/* Hero Visual: Split-screen animation */}
          <div className="mt-16 sm:mt-24">
            <div className="relative rounded-xl border border-border/50 bg-gradient-to-br from-background to-muted/30 p-2 shadow-2xl overflow-hidden">
              <div className="aspect-[16/9] rounded-lg bg-gradient-to-r from-muted/50 to-muted/30 flex items-center justify-center overflow-hidden relative">
                <div className="absolute inset-0 flex items-center justify-between px-4 sm:px-8">
                  {/* Left: Spreadsheet */}
                  <div className="flex-1 space-y-2">
                    <div className="text-xs font-semibold text-muted-foreground mb-3">Content Plan</div>
                    <div className="space-y-2">
                      {[1, 2, 3].map((i) => (
                        <div key={i} className="h-10 bg-muted/60 rounded border border-border/50 animate-pulse" />
                      ))}
                    </div>
                  </div>
                  
                  {/* Arrow indicator */}
                  <div className="flex items-center justify-center h-12 w-12 rounded-full bg-primary/10 mx-4 sm:mx-8 animate-pulse">
                    <ArrowRight className="h-6 w-6 text-primary" />
                  </div>
                  
                  {/* Right: Generated visuals grid */}
                  <div className="flex-1 grid grid-cols-2 gap-2">
                    {[1, 2, 3, 4].map((i) => (
                      <div key={i} className="aspect-square bg-gradient-to-br from-primary/20 to-primary/10 rounded border border-border/50 animate-pulse" />
                    ))}
                  </div>
                </div>
              </div>
            </div>
            <p className="text-center text-xs text-muted-foreground mt-4">Spreadsheet rows → post-ready visuals in minutes</p>
          </div>
        </div>
      </section>

      {/* SECTION 2: PAIN SECTION */}
      <section className="py-24 sm:py-32 bg-destructive/5 border-y border-border/40">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              Planning is easy. Production is the bottleneck.
            </h2>
            <p className="mt-4 text-lg text-muted-foreground">
              Social teams don't need more ideas. They need a faster way to produce.
            </p>
          </div>

          <div className="mx-auto mt-16 max-w-2xl">
            <div className="space-y-4">
              {painPoints.map((point, idx) => (
                <div key={idx} className="flex items-start gap-4 p-4 rounded-lg bg-card border border-border/50 hover:border-destructive/50 transition-colors">
                  <AlertCircle className="h-5 w-5 text-destructive shrink-0 mt-0.5" />
                  <p className="text-muted-foreground">{point}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* SECTION 3: SOLUTION */}
      <section className="py-24 sm:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center mb-16">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              Built for production — not one-off design.
            </h2>
            <p className="mt-4 text-lg text-muted-foreground">
              SnapDraft turns your content plan into batches of on-brand visuals. Not prompts. Not templates. A repeatable production workflow.
            </p>
          </div>

          <div className="mx-auto max-w-5xl grid gap-8 sm:grid-cols-3">
            {keyFeatures.map((feature, idx) => (
              <Card key={idx} className="border-border/50">
                <CardHeader>
                  <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 mb-4">
                    <feature.icon className="h-6 w-6 text-primary" />
                  </div>
                  <CardTitle className="text-xl">{feature.title}</CardTitle>
                  <CardDescription className="text-sm leading-relaxed mt-2">
                    {feature.description}
                  </CardDescription>
                </CardHeader>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* SECTION 4: HOW IT WORKS (3 STEPS) */}
      <section className="py-24 sm:py-32 bg-muted/30">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center mb-16">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              From plan to visuals in three steps.
            </h2>
          </div>

          <div className="mx-auto max-w-4xl grid gap-8 sm:grid-cols-3">
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
                <div className="rounded-2xl border border-border/50 bg-card p-8 text-center">
                  <div className="absolute -top-4 left-1/2 -translate-x-1/2">
                    <div className="h-8 w-8 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-sm font-bold">
                      {item.step}
                    </div>
                  </div>
                  <item.icon className="h-10 w-10 text-primary mx-auto mb-4 mt-2" />
                  <h3 className="text-lg font-semibold mb-2">{item.title}</h3>
                  <p className="text-sm text-muted-foreground">{item.description}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* SECTION 5: DESIGNER-FRIENDLY */}
      <section className="py-24 sm:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center mb-16">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              Not a designer replacement. A production accelerator.
            </h2>
            <p className="mt-4 text-lg text-muted-foreground">
              SnapDraft handles repetitive social production so designers can focus on creative work. Social managers can generate visuals themselves — designers stay in control of the final output.
            </p>
          </div>

          <div className="mx-auto max-w-4xl grid gap-8 sm:grid-cols-2">
            {[
              { title: 'Fewer briefs', icon: Briefcase },
              { title: 'Fewer repetitive layouts', icon: Layers },
              { title: 'Faster turnaround', icon: Zap },
              { title: 'Better use of design time', icon: RefreshCw },
            ].map((item, idx) => (
              <Card key={idx} className="border-border/50">
                <CardHeader>
                  <div className="flex items-center gap-3">
                    <item.icon className="h-6 w-6 text-primary" />
                    <CardTitle className="text-lg">{item.title}</CardTitle>
                  </div>
                </CardHeader>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* SECTION 6: USE CASES */}
      <section className="py-24 sm:py-32 bg-muted/30">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center mb-16">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              Built for real agency workflows.
            </h2>
          </div>

          <div className="mx-auto max-w-3xl grid gap-6 sm:grid-cols-2 mb-12">
            {useCases.map((useCase, idx) => (
              <Card key={idx} className="border-border/50 hover:border-primary/50 transition-colors">
                <CardHeader>
                  <CardTitle className="text-lg">{useCase.title}</CardTitle>
                  <CardDescription className="text-sm leading-relaxed">
                    {useCase.description}
                  </CardDescription>
                </CardHeader>
              </Card>
            ))}
          </div>

          <div className="text-center">
            <p className="text-lg font-semibold text-foreground">
              Same workflow. Different clients. Consistent results.
            </p>
          </div>
        </div>
      </section>

      {/* SECTION 7: CANVAS EDITOR */}
      <section className="py-24 sm:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center mb-16">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              Make final tweaks without restarting production.
            </h2>
            <p className="mt-4 text-lg text-muted-foreground">
              Adjust text, layout, or spacing directly in SnapDraft. No need to regenerate or jump between tools.
            </p>
          </div>

          <div className="mx-auto max-w-2xl">
            <div className="rounded-2xl border border-border/50 bg-gradient-to-br from-muted/50 to-muted/30 p-8 aspect-video flex items-center justify-center">
              <div className="text-center space-y-4">
                <Paintbrush className="h-12 w-12 text-primary mx-auto" />
                <p className="text-sm text-muted-foreground">Canvas Editor Demo</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* SECTION 8: DIFFERENTIATION */}
      <section className="py-24 sm:py-32 bg-muted/30">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center mb-16">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              Why agencies choose SnapDraft.
            </h2>
          </div>

          <div className="mx-auto max-w-4xl grid gap-8 sm:grid-cols-3">
            {differentiation.map((item, idx) => (
              <Card key={idx} className="border-border/50 text-center">
                <CardHeader>
                  <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-muted mx-auto mb-4">
                    <item.icon className="h-6 w-6 text-muted-foreground line-through" />
                  </div>
                  <CardTitle className="text-lg">{item.title}</CardTitle>
                  <CardDescription className="text-sm leading-relaxed mt-2">
                    {item.description}
                  </CardDescription>
                </CardHeader>
              </Card>
            ))}
          </div>

          <div className="text-center mt-16">
            <p className="text-lg font-semibold text-foreground">
              SnapDraft is built for volume, consistency, and speed.
            </p>
          </div>
        </div>
      </section>

      {/* SECTION 9: PRICING / TRIAL CTA */}
      <section className="py-24 sm:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center mb-16">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              Try SnapDraft with real production.
            </h2>
            <p className="mt-4 text-lg text-muted-foreground">
              A serious tool for serious workflows. No free toy. No long-term commitment.
            </p>
          </div>

          <div className="mx-auto max-w-md text-center">
            <Link href={register().url}>
              <Button size="lg" className="w-full gap-2 text-base h-12 px-8">
                Start your trial <ArrowRight className="h-4 w-4" />
              </Button>
            </Link>
            <p className="mt-4 text-sm text-muted-foreground">
              7-day trial · $7 · Cancel anytime
            </p>
          </div>
        </div>
      </section>

      {/* SECTION 10: FAQ */}
      <section className="py-24 sm:py-32 bg-muted/30">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center mb-16">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              Frequently asked questions
            </h2>
          </div>

          <div className="mx-auto max-w-2xl space-y-4">
            {faqs.map((faq, idx) => (
              <div key={idx} className="rounded-lg border border-border/50 bg-card overflow-hidden">
                <button
                  onClick={() => setExpandedFaq(expandedFaq === idx ? null : idx)}
                  className="w-full flex items-center justify-between p-6 hover:bg-muted/50 transition-colors text-left"
                >
                  <span className="font-semibold text-foreground">{faq.question}</span>
                  <ChevronDown
                    className={`h-5 w-5 text-muted-foreground shrink-0 transition-transform ${
                      expandedFaq === idx ? 'rotate-180' : ''
                    }`}
                  />
                </button>
                {expandedFaq === idx && (
                  <div className="px-6 pb-6 text-sm text-muted-foreground border-t border-border/50">
                    {faq.answer}
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* SECTION 11: FINAL CTA */}
      <section className="py-24 sm:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary/90 to-primary px-8 py-16 sm:px-16 sm:py-24 text-center">
            <div className="absolute inset-0 -z-10 bg-[radial-gradient(45rem_50rem_at_top,theme(colors.background/10%),transparent)]" />
            
            <h2 className="text-3xl font-bold tracking-tight text-primary-foreground sm:text-4xl font-sans">
              Stop waiting on production.
            </h2>
            <p className="mx-auto mt-4 max-w-xl text-lg text-primary-foreground/90">
              Join agencies creating thousands of brand-consistent visuals every week.
            </p>
            <div className="mt-8">
              <Link href={register().url}>
                <Button size="lg" variant="secondary" className="gap-2 text-base h-12 px-8">
                  Start your trial <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
              <p className="mt-4 text-sm text-primary-foreground/90">
                7-day trial · $7 · Cancel anytime
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-border/40">
        <div className="mx-auto max-w-7xl px-6 py-12">
          <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <div className="text-2xl font-bold tracking-tight font-sans text-muted-foreground">
              SnapDraft
            </div>
            <div className="flex items-center gap-6">
              <Link href={login().url} className="text-sm text-muted-foreground hover:text-foreground transition-colors">
                Sign in
              </Link>
              <Link href={register().url} className="text-sm text-muted-foreground hover:text-foreground transition-colors">
                Get started
              </Link>
            </div>
          </div>
          <div className="mt-8 border-t border-border/40 pt-8 text-center">
            <p className="text-sm text-muted-foreground">
              © {new Date().getFullYear()} SnapDraft. All rights reserved.
            </p>
          </div>
        </div>
      </footer>
    </div>
  );
}
