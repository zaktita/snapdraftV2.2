import { Head, Link } from '@inertiajs/react';
import { login, register } from '@/routes';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import '../../../css/landing.css';
import { cn } from '@/lib/utils';
import { 
  ArrowRight,
  Brain,
  Check,
  ChevronDown,
  Layout,
  LayoutGrid,
  Palette,
  Zap,
  Upload,
  ImageIcon,
  RefreshCw,
  Users,
  Menu,
  Play,
  Layers,
  Sparkles,
  FileSpreadsheet,
} from 'lucide-react';
import { useState } from 'react';

export default function Home() {
  const [expandedFaq, setExpandedFaq] = useState<number | null>(0);
  const [activeTab, setActiveTab] = useState('analysis');

  const features = [
    {
      id: 'analysis',
      title: 'Brand DNA',
      description: 'Upload assets, we extract the style.',
      icon: Palette
    },
    {
      id: 'csv',
      title: 'CSV Batch',
      description: 'Plan in sheets, generate in bulk.',
      icon: FileSpreadsheet
    },
    {
      id: 'generate',
      title: 'Production',
      description: 'One click, dozens of visuals.',
      icon: Zap
    }
  ];

  const faqs = [
    {
      question: "How does the brand analysis work?",
      answer: "We use computer vision to analyze your uploaded images, extracting hex codes, font styles, and layout patterns to build a custom generation model for your brand."
    },
    {
      question: "Can I edit the generated images?",
      answer: "Yes. Every generated image opens in our full-featured canvas editor where you can adjust layers, text, and composition."
    },
    {
      question: "Is my data private?",
      answer: "Your brand assets and generated content are isolated to your workspace. We do not use your proprietary data to train our public models."
    },
     {
      question: "What formats do you support?",
      answer: "We support Instagram Posts, Stories, LinkedIn Carousels, Pinterest Pins, and custom dimensions."
    }
  ];

  return (
    <div className="min-h-screen bg-slate-50 selection:bg-slate-200">
      <Head title="SnapDraft - AI Visual Production System" />

      {/* Navbar */}
      <header className="fixed top-0 z-50 w-full border-b border-white/50 bg-white/60 backdrop-blur-md">
        <div className="mx-auto flex h-14 max-w-5xl items-center justify-between px-4 sm:px-6">
          <Link href="/" className="flex items-center gap-2 font-medium tracking-tight text-slate-900">
            <svg
              className="h-6 w-6"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
            >
              <rect x="3" y="3" width="18" height="18" rx="2" className="text-slate-900" />
              <path d="M3 9h18" />
              <path d="M9 21V9" />
            </svg>
            SnapDraft
          </Link>
          <nav className="hidden gap-6 text-sm font-medium text-slate-600 md:flex">
            <a href="#features" className="hover:text-slate-900">Features</a>
            <a href="#how-it-works" className="hover:text-slate-900">How it works</a>
            <a href="#pricing" className="hover:text-slate-900">Pricing</a>
          </nav>
          <div className="flex items-center gap-4">
            <a href={login().url} className="text-sm font-medium text-slate-600 hover:text-slate-900">
              Log in
            </a>
            <Button asChild size="sm" className="h-8 rounded-full bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800">
              <Link href={register().url}>Get Started</Link>
            </Button>
          </div>
        </div>
      </header>

      <main className="pt-24 pb-16">
        {/* Hero Section */}
        <section className="mx-auto max-w-5xl px-4 sm:px-6">
          <div className="flex flex-col items-center text-center">
            <Badge variant="outline" className="mb-6 border-slate-200 bg-white px-3 py-1 text-slate-600 shadow-sm">
              <Sparkles className="mr-2 h-3 w-3 text-indigo-500" />
              v2.0 is now live
            </Badge>
            <h1 className="max-w-3xl text-balance font-sans text-5xl font-semibold tracking-tight text-slate-900 sm:text-7xl">
              Visual content at scale. No templates.
            </h1>
            <p className="mt-6 max-w-xl text-balance text-lg text-slate-500">
              Turn spreadsheets into brand-consistent visuals. The production system for marketing teams who need volume without sacrificing quality.
            </p>
            <div className="mt-10 flex items-center gap-4">
              <Button asChild size="lg" className="h-12 rounded-full px-8 text-base">
                <Link href={register().url}>Start building for free</Link>
              </Button>
              <Button asChild variant="ghost" size="lg" className="h-12 rounded-full px-8 text-base text-slate-600">
                <a href="#how-it-works">See how it works</a>
              </Button>
            </div>
            
            {/* Hero Visual showing interface snippet */}
            <div className="mt-16 w-full max-w-4xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
              <div className="border-b border-slate-100 bg-slate-50/50 px-4 py-3">
                <div className="flex gap-1.5">
                  <div className="h-2.5 w-2.5 rounded-full bg-slate-200" />
                  <div className="h-2.5 w-2.5 rounded-full bg-slate-200" />
                  <div className="h-2.5 w-2.5 rounded-full bg-slate-200" />
                </div>
              </div>
              <div className="aspect-[16/10] w-full bg-slate-50 relative">
                  <img src="/images/landing/image 2.png" alt="SnapDraft Interface" className="object-cover w-full h-full" />
              </div>
            </div>
          </div>
        </section>

        {/* Features Grid (Bento) */}
        <section id="features" className="mx-auto mt-32 max-w-5xl px-4 sm:px-6">
          <div className="mb-12">
            <h2 className="text-3xl font-semibold tracking-tight text-slate-900">Everything you need to ship.</h2>
            <p className="mt-2 text-slate-500">Built for modern marketing workflows.</p>
          </div>
          
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 md:grid-rows-2 h-[800px] sm:h-[500px]">
            {/* Feature 1: Large Left */}
            <div className="group relative col-span-1 overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 sm:col-span-2 sm:row-span-2 ">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-100 bg-slate-50 text-slate-900 mb-4">
               <LayoutGrid className="h-5 w-5" />
              </div>
              <h3 className="text-lg font-semibold text-slate-900">Structured Generation</h3>
              <p className="mt-2 max-w-xs text-sm text-slate-500">
                We don't use random prompts. Our AI understands layout, typography, and brand rules to generate consistent output every time.
              </p>
              <div className="absolute bottom-6 right-6 w-48 rounded-lg border border-slate-100 shadow-lg bg-white p-3 rotate-3 transition-transform group-hover:rotate-0">
                  <div className="h-2 w-12 rounded bg-slate-100 mb-2"></div>
                  <div className="h-16 w-full rounded bg-slate-50"></div>
              </div>
            </div>

            {/* Feature 2: Top Right */}
            <div className="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6">
               <Palette className="h-6 w-6 text-slate-900 mb-3" />
               <h3 className="font-semibold text-slate-900">Brand DNA</h3>
               <p className="mt-1 text-xs text-slate-500">Upload assets, we extract the style.</p>
            </div>

             {/* Feature 3: Bottom Right */}
            <div className="group relative col-span-1 overflow-hidden rounded-2xl border border-slate-200 bg-indigo-600 p-6 text-white sm:col-span-1">
               <Zap className="h-6 w-6 text-white mb-3" />
               <h3 className="font-semibold">Lightning Fast</h3>
               <p className="mt-1 text-xs text-white/80">Generate 100+ assets in minutes.</p>
            </div>
          </div>
        </section>

        {/* How it Works - Tabs */}
        <section id="how-it-works" className="mx-auto mt-32 max-w-5xl px-4 sm:px-6">
          <div className="grid gap-12 lg:grid-cols-2">
            <div>
              <h2 className="text-3xl font-semibold tracking-tight text-slate-900">Designed for flow.</h2>
              <p className="mt-2 text-slate-500 mb-8">
                A simple, linear process that fits into your existing content workflow.
              </p>
              
              <div className="flex flex-col gap-2">
                {features.map((feature) => (
                    <button
                        key={feature.id}
                        onClick={() => setActiveTab(feature.id)}
                        className={cn(
                            "group flex items-start gap-4 rounded-xl p-4 text-left transition-all",
                            activeTab === feature.id 
                                ? "bg-white shadow-sm ring-1 ring-slate-200" 
                                : "hover:bg-slate-50"
                        )}
                    >
                        <div className={cn(
                            "flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border transition-colors",
                            activeTab === feature.id
                                ? "border-slate-200 bg-slate-50 text-slate-900"
                                : "border-transparent bg-transparent text-slate-400 group-hover:text-slate-600"
                        )}>
                            <feature.icon className="h-4 w-4" />
                        </div>
                        <div>
                            <h3 className={cn(
                                "font-semibold", 
                                activeTab === feature.id ? "text-slate-900" : "text-slate-600"
                            )}>
                                {feature.title}
                            </h3>
                            <p className="mt-1 text-sm text-slate-500">
                                {feature.description}
                            </p>
                        </div>
                    </button>
                ))}
              </div>
            </div>
            
            <div className="relative flex items-center justify-center rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                {/* Dynamic Image based on active Tab */}
                 {activeTab === 'analysis' && (
                     <div className="text-center">
                         <div className="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 mb-4">
                             <Palette className="h-8 w-8" />
                         </div>
                         <p className="text-sm font-medium text-slate-900">Analyzing Color Palette...</p>
                         <div className="mt-2 flex justify-center gap-1">
                             <div className="h-6 w-6 rounded-full bg-[#FF5733]"></div>
                             <div className="h-6 w-6 rounded-full bg-[#33FF57]"></div>
                             <div className="h-6 w-6 rounded-full bg-[#3357FF]"></div>
                         </div>
                     </div>
                 )}
                 {activeTab === 'csv' && (
                     <div className="w-full max-w-xs rounded-lg border border-slate-100 bg-slate-50 p-3 font-mono text-xs">
                         <div className="flex gap-4 border-b border-slate-200 pb-2 text-slate-400">
                             <span>Title</span>
                             <span>Format</span>
                         </div>
                         <div className="flex gap-4 py-2 text-slate-700">
                             <span>Summer Sale</span>
                             <span>Story</span>
                         </div>
                         <div className="flex gap-4 py-2 text-slate-700">
                             <span>New Arrival</span>
                             <span>Post</span>
                         </div>
                     </div>
                 )}
                 {activeTab === 'generate' && (
                     <div className="grid grid-cols-2 gap-2 w-full max-w-xs">
                         <div className="aspect-square rounded-lg bg-slate-100 animate-pulse"></div>
                         <div className="aspect-square rounded-lg bg-slate-100 animate-pulse delay-75"></div>
                         <div className="aspect-square rounded-lg bg-slate-100 animate-pulse delay-150"></div>
                     </div>
                 )}
            </div>
          </div>
        </section>

        {/* FAQ Section */}
        <section id="faq" className="mx-auto mt-32 max-w-3xl px-4 sm:px-6">
             <h2 className="mb-10 text-center text-2xl font-semibold text-slate-900">Common Questions</h2>
             <div className="space-y-4">
                {faqs.map((faq, idx) => (
                    <div key={idx} className="rounded-lg border border-slate-200 bg-white px-6 py-4">
                        <h4 className="font-medium text-slate-900">{faq.question}</h4>
                        <p className="mt-2 text-sm text-slate-500">{faq.answer}</p>
                    </div>
                ))}
             </div>
        </section>

        {/* CTA */}
        <section className="mt-32 border-t border-slate-200 bg-slate-50 py-24 text-center">
             <h2 className="text-3xl font-semibold tracking-tight text-slate-900">Ready to scale your output?</h2>
             <div className="mt-8">
                 <Button asChild size="lg" className="h-12 rounded-full px-8">
                    <Link href={register().url}>Get Started Free</Link>
                 </Button>
             </div>
        </section>

      </main>

      <footer className="border-t border-slate-200 bg-white py-12">
        <div className="mx-auto flex max-w-5xl flex-col items-center justify-between gap-4 px-6 md:flex-row">
            <p className="text-sm text-slate-500">© 2024 SnapDraft. All rights reserved.</p>
            <div className="flex gap-6 text-sm text-slate-500">
                <a href="#" className="hover:text-slate-900">Terms</a>
                <a href="#" className="hover:text-slate-900">Privacy</a>
            </div>
        </div>
      </footer>
    </div>
  );
}
