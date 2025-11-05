import { Head, Link } from '@inertiajs/react';
import { login, register } from '@/routes';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Sparkles, Zap, Paintbrush, ArrowRight, Upload, FileSpreadsheet, Image as ImageIcon, Download, Check, TrendingUp, Users, Clock } from 'lucide-react';

export default function Home() {
  const features = [
    {
      icon: Sparkles,
      title: 'AI-Powered Generation',
      description: 'Transform CSV data into stunning visuals with intelligent AI that learns your brand style.',
    },
    {
      icon: Zap,
      title: 'Batch Processing',
      description: 'Generate hundreds of on-brand images at once. Upload your CSV and let AI handle the rest.',
    },
    {
      icon: Paintbrush,
      title: 'Canvas Editor',
      description: 'Fine-tune every detail with AI-powered retouching tools. Replace text, erase, and regenerate with precision.',
    },
  ];

  const workflow = [
    {
      icon: Upload,
      title: 'Upload Brand References',
      description: 'Add 5-10 images that represent your brand style. Our AI analyzes colors, typography, and composition.',
      color: 'from-blue-500 to-cyan-500',
    },
    {
      icon: FileSpreadsheet,
      title: 'Paste Your CSV',
      description: 'Simple format: title, description, format. Each row becomes a unique, on-brand visual.',
      color: 'from-purple-500 to-pink-500',
    },
    {
      icon: ImageIcon,
      title: 'Generate in Batch',
      description: 'Watch as AI creates dozens or hundreds of images in minutes, maintaining perfect brand consistency.',
      color: 'from-orange-500 to-red-500',
    },
    {
      icon: Download,
      title: 'Export & Use',
      description: 'Download individual files or bulk export. Perfect for social media, ads, presentations, and more.',
      color: 'from-green-500 to-emerald-500',
    },
  ];

  const useCases = [
    {
      title: 'Social Media Content',
      description: 'Create hundreds of post variations for Instagram, Facebook, and LinkedIn in one batch.',
      stats: '100+ posts/hour',
    },
    {
      title: 'E-commerce Product Cards',
      description: 'Generate product visuals with descriptions at scale. Maintain brand consistency across catalogs.',
      stats: '10x faster',
    },
    {
      title: 'Marketing Campaigns',
      description: 'Launch campaigns with dozens of ad variations. A/B test creatives without designer bottlenecks.',
      stats: '90% cost savings',
    },
    {
      title: 'Presentations & Reports',
      description: 'Turn data into compelling visuals. Perfect for sales decks, quarterly reports, and client pitches.',
      stats: 'Professional quality',
    },
  ];

  const stats = [
    { icon: TrendingUp, value: '10,000+', label: 'Images generated' },
    { icon: Users, value: '500+', label: 'Active users' },
    { icon: Clock, value: '5 min', label: 'Average generation time' },
  ];

  const pricing = [
    {
      name: 'Free',
      price: '$0',
      description: 'Perfect for trying out SnapDraft',
      features: [
        '50 credits/month',
        'Up to 10 images per batch',
        'Basic canvas editor',
        'Standard quality',
        'Community support',
      ],
      cta: 'Start free',
      variant: 'outline' as const,
    },
    {
      name: 'Pro',
      price: '$29',
      description: 'For professionals and small teams',
      features: [
        '500 credits/month',
        'Unlimited batch size',
        'Advanced canvas editor',
        'High quality + Fast generation',
        'Priority support',
        'Custom brand presets',
      ],
      cta: 'Start Pro trial',
      variant: 'default' as const,
      popular: true,
    },
    {
      name: 'Enterprise',
      price: 'Custom',
      description: 'For agencies and large teams',
      features: [
        'Unlimited credits',
        'White-label option',
        'API access',
        'Dedicated account manager',
        'SLA guarantee',
        'Custom AI training',
      ],
      cta: 'Contact sales',
      variant: 'outline' as const,
    },
  ];

  return (
    <div className="min-h-dvh bg-background">
      <Head title="SnapDraft - AI-Powered Visual Content Generator" />

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
                Get started <ArrowRight className="h-4 w-4" />
              </Button>
            </Link>
          </div>
        </nav>
      </header>

      {/* Hero Section */}
      <section className="relative overflow-hidden pt-32 pb-24 sm:pt-40 sm:pb-32">
        <div className="absolute inset-0 -z-10 bg-[radial-gradient(45rem_50rem_at_top,theme(colors.primary.200/20%),transparent)] dark:bg-[radial-gradient(45rem_50rem_at_top,theme(colors.primary.900/20%),transparent)]" />
        
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-3xl text-center">
            <Badge variant="secondary" className="mb-4 gap-2">
              <Sparkles className="h-3 w-3" />
              <span>Powered by AI</span>
            </Badge>
            
            <h1 className="text-5xl font-bold tracking-tight sm:text-7xl font-sans bg-gradient-to-br from-foreground to-foreground/60 bg-clip-text text-transparent">
              Brand-consistent visuals from CSV data
            </h1>
            
            <p className="mt-6 text-lg leading-8 text-muted-foreground max-w-2xl mx-auto">
              Upload your brand references, paste your content, and generate stunning, on-brand images in minutes. No design skills required.
            </p>
            
            <div className="mt-10 flex items-center justify-center gap-4">
              <Link href={register().url}>
                <Button size="lg" className="gap-2 text-base h-12 px-8">
                  Start creating <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
              <Link href={login().url}>
                <Button size="lg" variant="outline" className="text-base h-12 px-8">
                  Sign in
                </Button>
              </Link>
            </div>
            
            <p className="mt-4 text-sm text-muted-foreground">
              Free to start · No credit card required
            </p>
          </div>

          {/* Hero Visual */}
          <div className="mt-16 sm:mt-24">
            <div className="relative rounded-xl border border-border/50 bg-gradient-to-br from-background to-muted/30 p-2 shadow-2xl">
              <div className="aspect-[16/9] rounded-lg bg-muted/50 flex items-center justify-center overflow-hidden">
                <div className="text-center space-y-4 p-8">
                  <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary/10 text-primary text-sm font-medium">
                    <Sparkles className="h-4 w-4" />
                    AI Canvas Editor
                  </div>
                  <p className="text-muted-foreground text-sm max-w-md mx-auto">
                    Interactive demo coming soon - see how AI transforms your images with precision mask editing
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="py-24 sm:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              Everything you need to create at scale
            </h2>
            <p className="mt-4 text-lg text-muted-foreground">
              From CSV to canvas, SnapDraft handles the entire workflow
            </p>
          </div>

          <div className="mx-auto mt-16 grid max-w-5xl gap-8 sm:grid-cols-2 lg:grid-cols-3">
            {features.map((feature, idx) => (
              <div
                key={idx}
                className="relative overflow-hidden rounded-2xl border border-border/50 bg-card p-8 hover:border-primary/50 transition-colors"
              >
                <div className="flex items-center gap-4">
                  <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                    <feature.icon className="h-6 w-6 text-primary" />
                  </div>
                </div>
                <h3 className="mt-4 text-xl font-semibold">{feature.title}</h3>
                <p className="mt-2 text-sm text-muted-foreground leading-relaxed">
                  {feature.description}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* How it Works */}
      <section className="py-24 sm:py-32 bg-muted/30">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center">
            <Badge variant="secondary" className="mb-4">How it works</Badge>
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              From data to visuals in 4 simple steps
            </h2>
            <p className="mt-4 text-lg text-muted-foreground">
              No design experience needed. Just upload, paste, and generate.
            </p>
          </div>

          <div className="mx-auto mt-16 grid max-w-6xl gap-8 lg:grid-cols-2">
            {workflow.map((step, idx) => (
              <div key={idx} className="relative overflow-hidden rounded-2xl border border-border/50 bg-card p-8">
                <div className="flex items-start gap-6">
                  <div className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${step.color} text-white`}>
                    <step.icon className="h-7 w-7" />
                  </div>
                  <div>
                    <div className="text-sm font-medium text-muted-foreground mb-2">Step {idx + 1}</div>
                    <h3 className="text-xl font-semibold mb-3">{step.title}</h3>
                    <p className="text-sm text-muted-foreground leading-relaxed">
                      {step.description}
                    </p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Use Cases */}
      <section className="py-24 sm:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              Built for modern teams
            </h2>
            <p className="mt-4 text-lg text-muted-foreground">
              From startups to enterprises, SnapDraft powers visual content creation
            </p>
          </div>

          <div className="mx-auto mt-16 grid max-w-6xl gap-8 sm:grid-cols-2">
            {useCases.map((useCase, idx) => (
              <Card key={idx} className="border-border/50 hover:border-primary/50 transition-colors">
                <CardHeader>
                  <div className="flex items-start justify-between">
                    <CardTitle className="text-xl">{useCase.title}</CardTitle>
                    <Badge variant="secondary" className="text-xs">{useCase.stats}</Badge>
                  </div>
                  <CardDescription className="text-sm leading-relaxed mt-2">
                    {useCase.description}
                  </CardDescription>
                </CardHeader>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Stats */}
      <section className="py-16 border-y border-border/40 bg-muted/20">
        <div className="mx-auto max-w-7xl px-6">
          <div className="grid gap-8 sm:grid-cols-3">
            {stats.map((stat, idx) => (
              <div key={idx} className="text-center">
                <div className="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 mb-4">
                  <stat.icon className="h-6 w-6 text-primary" />
                </div>
                <div className="text-3xl font-bold">{stat.value}</div>
                <div className="text-sm text-muted-foreground mt-1">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing */}
      <section className="py-24 sm:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl font-sans">
              Simple, transparent pricing
            </h2>
            <p className="mt-4 text-lg text-muted-foreground">
              Start free, upgrade when you need more power
            </p>
          </div>

          <div className="mx-auto mt-16 grid max-w-6xl gap-8 lg:grid-cols-3">
            {pricing.map((plan, idx) => (
              <Card key={idx} className={`relative ${plan.popular ? 'border-primary shadow-lg ring-2 ring-primary/20' : 'border-border/50'}`}>
                {plan.popular && (
                  <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <Badge className="bg-primary text-primary-foreground">Most popular</Badge>
                  </div>
                )}
                <CardHeader className="pb-8">
                  <CardTitle className="text-2xl">{plan.name}</CardTitle>
                  <div className="mt-4 flex items-baseline gap-1">
                    <span className="text-4xl font-bold tracking-tight">{plan.price}</span>
                    {plan.price !== 'Custom' && <span className="text-muted-foreground">/month</span>}
                  </div>
                  <CardDescription className="text-sm mt-2">{plan.description}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                  <ul className="space-y-3">
                    {plan.features.map((feature, featureIdx) => (
                      <li key={featureIdx} className="flex items-start gap-3 text-sm">
                        <Check className="h-5 w-5 text-primary shrink-0 mt-0.5" />
                        <span className="text-muted-foreground">{feature}</span>
                      </li>
                    ))}
                  </ul>
                  <Link href={register().url}>
                    <Button variant={plan.variant} className="w-full">
                      {plan.cta}
                    </Button>
                  </Link>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-24 sm:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary/90 to-primary px-8 py-16 sm:px-16 sm:py-24 text-center">
            <div className="absolute inset-0 -z-10 bg-[radial-gradient(45rem_50rem_at_top,theme(colors.background/10%),transparent)]" />
            
            <h2 className="text-3xl font-bold tracking-tight text-primary-foreground sm:text-4xl font-sans">
              Ready to transform your workflow?
            </h2>
            <p className="mx-auto mt-4 max-w-xl text-lg text-primary-foreground/90">
              Join teams creating thousands of brand-consistent visuals with AI
            </p>
            <div className="mt-8">
              <Link href={register().url}>
                <Button size="lg" variant="secondary" className="gap-2 text-base h-12 px-8">
                  Get started for free <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
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
