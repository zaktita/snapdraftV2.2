import { Head, Link } from '@inertiajs/react';
import { login, register } from '@/routes';
import { useState } from 'react';
import { Check, ChevronDown, Play } from 'lucide-react';

export default function Startup() {
  const [pricingPeriod, setPricingPeriod] = useState<'monthly' | 'yearly'>('yearly');
  const [expandedFaq, setExpandedFaq] = useState<number>(0);

  const services = [
    {
      icon: 'assets/images/v3/icon1.png',
      title: 'Brand DNA Analysis',
      description: 'AI-powered platform that extracts colors, typography, and layout patterns.',
      link: '#'
    },
    {
      icon: 'assets/images/v3/icon2.png',
      title: 'Batch Generation',
      description: 'A CSV-based workflow that helps plan, organize and generate visuals.',
      link: '#'
    },
    {
      icon: 'assets/images/v3/icon3.png',
      title: 'Canvas Editor',
      description: 'A powerful editing platform with fine-tuning features.',
      link: '#'
    },
    {
      icon: 'assets/images/v3/icon4.png',
      title: 'Multi-Brand Support',
      description: 'An all-in-one brand management system designed for agencies.',
      link: '#'
    },
    {
      icon: 'assets/images/v3/icon5.png',
      title: 'Export & Publishing',
      description: 'Export tools that help with format conversion and asset management.',
      link: '#'
    },
    {
      icon: 'assets/images/v3/icon6.png',
      title: 'AI Security',
      description: 'Security platform that provides protection for your brand assets.',
      link: '#'
    }
  ];

  const pricingPlans = [
    {
      name: 'Free',
      price: { monthly: 0, yearly: 0 },
      description: 'This plan is typically limited in features and usage',
      features: [
        'Basic features',
        'Limited users & usage',
        'No customer support',
        '30 day chat history',
        '10 Integrations'
      ]
    },
    {
      name: 'Basic',
      price: { monthly: 19, yearly: 39 },
      description: 'A simple and affordable plan only for small businesses',
      features: [
        'Core features',
        'Increased limits',
        'Priority support',
        'Unlimited chat history',
        '20 Integrations'
      ]
    },
    {
      name: 'Standard',
      price: { monthly: 29, yearly: 59 },
      description: 'This plan is geared toward growing businesses',
      isPopular: true,
      features: [
        'Expanded features',
        'Increased users',
        'Priority email support',
        'Unlimited chat history',
        '30 Integrations'
      ]
    },
    {
      name: 'Enterprise',
      price: { monthly: 59, yearly: 99 },
      description: 'Tailored to the unique needs of large enterprises',
      features: [
        'Customized features',
        'Scalability & security',
        'Account manager',
        'Unlimited chat history',
        '50 Integrations'
      ]
    }
  ];

  const faqs = [
    {
      question: 'Q: What makes your SaaS solution stand out from competitors?',
      answer: 'Our SaaS platform distinguishes itself through a combination of user-friendly design, robust feature set, and a commitment to constant innovation. We prioritize customer feedback to ensure we stay ahead of the curve and meet evolving business needs.'
    },
    {
      question: 'Q: How secure is the data stored on your platform?',
      answer: 'Security is our top priority. We employ state-of-the-art encryption, regular security audits, and compliance with industry standards to safeguard your data. Our commitment is to provide a secure environment for your valuable information.'
    },
    {
      question: 'Q: Can your SaaS solution integrate with other tools we use?',
      answer: 'Absolutely. We understand the importance of seamless integration. Our SaaS solution is designed to work harmoniously with a variety of popular tools and platforms, ensuring a smooth workflow and reducing any disruptions to your current processes.'
    },
    {
      question: 'Q: What kind of customer support can we expect?',
      answer: 'Our customer support team is dedicated to your success. You can expect prompt responses, helpful resources, and, if needed, personalized assistance. We believe in building long-lasting relationships with our users, and exceptional support.'
    },
    {
      question: 'Q: How does your pricing model work, and is there flexibility as our business grows?',
      answer: 'We offer a range of flexible pricing plans to accommodate businesses of all sizes. Whether you\'re a startup or an enterprise, our pricing scales with your needs. You can choose from monthly or annual billing.'
    }
  ];

  return (
    <>
      <Head title="SnapDraft - Launch your startup's success with SaaS" />
      
      {/* Header */}
      <header className="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur-sm">
        <div className="container mx-auto px-4">
          <nav className="flex h-20 items-center justify-between">
            <Link href="/" className="flex items-center">
              <img
                src="/SnapdraftLogoBlack.svg"
                alt="SnapDraft"
                className="h-8 w-auto"
              />
            </Link>

            <div className="hidden items-center gap-8 lg:flex">
              <a href="#features" className="font-raleway text-sm font-semibold text-slate-700 transition-colors hover:text-slate-900">
                Features
              </a>
              <a href="#how-it-works" className="font-raleway text-sm font-semibold text-slate-700 transition-colors hover:text-slate-900">
                How it works
              </a>
              <a href="#pricing" className="font-raleway text-sm font-semibold text-slate-700 transition-colors hover:text-slate-900">
                Pricing
              </a>
              <a href="#faq" className="font-raleway text-sm font-semibold text-slate-700 transition-colors hover:text-slate-900">
                FAQ
              </a>
            </div>

            <div className="flex items-center gap-4">
              <a
                href={login().url}
                className="hidden text-sm font-semibold text-slate-700 transition-colors hover:text-slate-900 sm:block"
              >
                Login
              </a>
              <Link
                href={register().url}
                className="rounded-full bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white transition-all hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-600/30"
              >
                Get Started
              </Link>
            </div>
          </nav>
        </div>
      </header>

      {/* Hero Section */}
      <section className="relative overflow-hidden bg-gradient-to-b from-indigo-50 to-white py-20 lg:py-28">
        <div className="container mx-auto px-4">
          <div className="mx-auto max-w-4xl text-center">
            <h1 className="font-raleway text-5xl font-bold leading-tight tracking-tight text-slate-900 lg:text-6xl xl:text-7xl">
              Launch your startup's success with{' '}
              <span className="relative inline-block">
                SaaS
                <img
                  src="assets/images/v3/shape-v3-01.png"
                  alt=""
                  className="absolute -bottom-2 left-0 w-full"
                />
              </span>
            </h1>
            <p className="mx-auto mt-6 max-w-2xl text-lg text-slate-600">
              Our SaaS for startup businesses offers a cost-effective, scalable and user-friendly approach to access and use the software tools they need and provides powerful technology solutions.
            </p>

            <div className="mt-10">
              <form className="mx-auto flex max-w-lg flex-col gap-4 sm:flex-row">
                <div className="relative flex-1">
                  <input
                    type="email"
                    placeholder="Enter your e-mail address"
                    className="h-14 w-full rounded-full border border-slate-200 bg-white px-6 pr-12 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                  />
                  <img
                    src="assets/images/icon/email2.svg"
                    alt=""
                    className="absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2"
                  />
                </div>
                <button
                  type="submit"
                  className="h-14 whitespace-nowrap rounded-full bg-indigo-600 px-8 text-sm font-semibold text-white transition-all hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-600/30"
                >
                  Get started
                </button>
              </form>
              
              <div className="mt-6 flex flex-wrap items-center justify-center gap-4 text-sm text-slate-600">
                <div className="flex items-center gap-2">
                  <img src="assets/images/v3/check.png" alt="" className="h-5 w-5" />
                  <span>No credit card required</span>
                </div>
                <div className="flex items-center gap-2">
                  <img src="assets/images/v3/check.png" alt="" className="h-5 w-5" />
                  <span>7-day free trial</span>
                </div>
              </div>
            </div>
          </div>

          {/* Hero Image */}
          <div className="relative mx-auto mt-16 max-w-5xl border-b border-slate-200 pb-16">
            <div className="relative">
              <img
                src="assets/images/v3/thumb-v3-01.png"
                alt="Dashboard"
                className="w-full rounded-2xl shadow-2xl"
              />
              
              {/* Floating Cards */}
              <img
                src="assets/images/v3/card-v3-1.png"
                alt=""
                className="absolute -left-4 top-1/4 hidden w-48 animate-float md:block"
              />
              <img
                src="assets/images/v3/card-v3-2.png"
                alt=""
                className="absolute -right-4 top-1/3 hidden w-48 animate-float animation-delay-200 lg:block"
              />
              <img
                src="assets/images/v3/card-v3-3.png"
                alt=""
                className="absolute bottom-8 left-1/4 hidden w-48 animate-float animation-delay-400 md:block"
              />
            </div>
          </div>
        </div>
      </section>

      {/* Services Section */}
      <section id="features" className="py-20 lg:py-28">
        <div className="container mx-auto px-4">
          <div className="mb-16 flex flex-col items-start justify-between gap-6 lg:flex-row lg:items-center">
            <div className="max-w-2xl">
              <h2 className="font-raleway text-4xl font-bold tracking-tight text-slate-900 lg:text-5xl">
                Wide range of SaaS solutions
              </h2>
            </div>
            <a
              href="#"
              className="rounded-full bg-indigo-600 px-8 py-3 text-sm font-semibold text-white transition-all hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-600/30"
            >
              View all services
            </a>
          </div>

          <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
            {services.map((service, idx) => (
              <div
                key={idx}
                className="group rounded-2xl border-l-4 border-indigo-600 bg-white p-8 shadow-sm transition-all hover:shadow-lg"
              >
                <div className="mb-4">
                  <img src={service.icon} alt="" className="h-14 w-14" />
                </div>
                <h3 className="font-raleway text-xl font-semibold text-slate-900">
                  {service.title}
                </h3>
                <p className="mt-3 text-sm leading-relaxed text-slate-600">
                  {service.description}
                </p>
                <a
                  href={service.link}
                  className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 transition-colors hover:text-indigo-700"
                >
                  <span>Read more</span>
                  <img src="assets/images/icon/arrow-right2.svg" alt="" className="h-4 w-4" />
                </a>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Agility Section */}
      <section id="how-it-works" className="bg-slate-50 py-20 lg:py-28">
        <div className="container mx-auto px-4">
          <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
            <div className="relative">
              <img
                src="assets/images/v3/thumb-v3-2.png"
                alt="Agility"
                className="w-full rounded-2xl shadow-xl"
              />
              <div className="absolute -right-8 -top-8 hidden lg:block">
                <img
                  src="assets/images/v3/card-v3-4.png"
                  alt=""
                  className="w-64 animate-float"
                />
              </div>
            </div>

            <div>
              <h2 className="font-raleway text-4xl font-bold tracking-tight text-slate-900 lg:text-5xl">
                Agility to adapt to market needs
              </h2>
              <p className="mt-6 text-lg leading-relaxed text-slate-600">
                Startups often need to bring their products or services to market quickly. Our SaaS applications are readily available and reducing development and deployment time.
              </p>

              <div className="mt-12 space-y-8">
                <div className="flex gap-6">
                  <div className="flex-shrink-0">
                    <img src="assets/images/v3/icon11.png" alt="" className="h-14 w-14" />
                  </div>
                  <div>
                    <h3 className="font-raleway text-lg font-semibold text-slate-900">
                      Fast implementation:
                    </h3>
                    <p className="mt-2 text-sm leading-relaxed text-slate-600">
                      Our SaaS solutions can be implemented quickly, often within a few clicks, reducing setup time and allowing startups immediately.
                    </p>
                  </div>
                </div>

                <div className="flex gap-6">
                  <div className="flex-shrink-0">
                    <img src="assets/images/v3/icon10.png" alt="" className="h-14 w-14" />
                  </div>
                  <div>
                    <h3 className="font-raleway text-lg font-semibold text-slate-900">
                      Accelerated time-to-market:
                    </h3>
                    <p className="mt-2 text-sm leading-relaxed text-slate-600">
                      SaaS tools startups can accelerate their product or service launch and stay ahead of the competition and reach customers faster.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Cost-Effective Section */}
      <section className="bg-slate-900 py-20 text-white lg:py-28">
        <div className="container mx-auto px-4">
          <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
            <div className="order-2 lg:order-1">
              <h2 className="font-raleway text-4xl font-bold tracking-tight lg:text-5xl">
                Cost-effective & simple process
              </h2>

              <div className="mt-12 space-y-8">
                <div className="flex gap-6">
                  <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-600 font-raleway text-xl font-bold">
                    1
                  </div>
                  <div>
                    <h3 className="font-raleway text-lg font-semibold">
                      Subscription-based model:
                    </h3>
                    <p className="mt-2 text-sm leading-relaxed text-slate-300">
                      Our SaaS operates on a subscription-based payment structure, which allows users to pay for the software on a regular basis.
                    </p>
                  </div>
                </div>

                <div className="flex gap-6">
                  <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-600 font-raleway text-xl font-bold">
                    2
                  </div>
                  <div>
                    <h3 className="font-raleway text-lg font-semibold">
                      Cloud hosting:
                    </h3>
                    <p className="mt-2 text-sm leading-relaxed text-slate-300">
                      SaaS applications are hosted on cloud servers, eliminating the need for users to manage their own infrastructure costs.
                    </p>
                  </div>
                </div>

                <div className="flex gap-6">
                  <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-600 font-raleway text-xl font-bold">
                    3
                  </div>
                  <div>
                    <h3 className="font-raleway text-lg font-semibold">
                      Automated updates and maintenance:
                    </h3>
                    <p className="mt-2 text-sm leading-relaxed text-slate-300">
                      Manage software updates, security patches and maintenance so that users always have access to the latest features and secure.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div className="relative order-1 lg:order-2">
              <img
                src="assets/images/v3/thumb-v3-3.png"
                alt="Process"
                className="w-full rounded-2xl shadow-xl"
              />
              <div className="absolute -left-8 bottom-8 hidden lg:block">
                <img
                  src="assets/images/v3/card-v3-5.png"
                  alt=""
                  className="w-64 animate-float"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Stats Section */}
      <section className="bg-slate-900 py-16">
        <div className="container mx-auto px-4">
          <div className="grid gap-8 text-center md:grid-cols-2 lg:grid-cols-4">
            <div className="text-white">
              <div className="font-raleway text-5xl font-bold lg:text-6xl">
                99.5<span className="text-indigo-400">%</span>
              </div>
              <p className="mt-3 text-sm text-slate-300">Positive user rating</p>
            </div>
            <div className="text-white">
              <div className="font-raleway text-5xl font-bold lg:text-6xl">
                2<span className="text-indigo-400">K</span>
              </div>
              <p className="mt-3 text-sm text-slate-300">Monthly active user</p>
            </div>
            <div className="text-white">
              <div className="font-raleway text-5xl font-bold lg:text-6xl">
                100<span className="text-indigo-400">%</span>
              </div>
              <p className="mt-3 text-sm text-slate-300">Uptime in the last year</p>
            </div>
            <div className="text-white">
              <div className="font-raleway text-5xl font-bold lg:text-6xl">
                55<span className="text-indigo-400">+</span>
              </div>
              <p className="mt-3 text-sm text-slate-300">Integration with other tools</p>
            </div>
          </div>
        </div>
      </section>

      {/* Pricing Section */}
      <section id="pricing" className="py-20 lg:py-28">
        <div className="container mx-auto px-4">
          <div className="mb-12 text-center">
            <h2 className="font-raleway text-4xl font-bold tracking-tight text-slate-900 lg:text-5xl">
              Rational planning for rapid growth
            </h2>
          </div>

          {/* Pricing Toggle */}
          <div className="mb-12 flex justify-center">
            <div className="inline-flex items-center gap-4 rounded-full bg-slate-100 p-1.5">
              <span className="px-4 py-2 text-sm font-semibold text-slate-700">Monthly</span>
              <button
                onClick={() => setPricingPeriod(pricingPeriod === 'monthly' ? 'yearly' : 'monthly')}
                className={`relative h-6 w-12 rounded-full transition-colors ${
                  pricingPeriod === 'yearly' ? 'bg-indigo-600' : 'bg-slate-300'
                }`}
              >
                <span
                  className={`absolute top-0.5 h-5 w-5 rounded-full bg-white transition-transform ${
                    pricingPeriod === 'yearly' ? 'translate-x-6' : 'translate-x-0.5'
                  }`}
                />
              </button>
              <span className="px-4 py-2 text-sm font-semibold text-slate-700">Annually</span>
            </div>
          </div>

          {/* Pricing Cards */}
          <div className="grid gap-8 lg:grid-cols-4">
            {pricingPlans.map((plan, idx) => (
              <div
                key={idx}
                className={`relative rounded-2xl border bg-white p-8 shadow-sm transition-all hover:shadow-lg ${
                  plan.isPopular ? 'border-indigo-600 shadow-indigo-600/10' : 'border-slate-200'
                }`}
              >
                <div className="mb-6">
                  <h3 className="font-raleway text-xl font-semibold text-slate-900">
                    {plan.name}
                  </h3>
                </div>

                <div className="mb-4 flex items-start">
                  <span className="font-raleway text-3xl font-bold text-slate-900">$</span>
                  <span className="font-raleway text-5xl font-bold text-slate-900">
                    {plan.price[pricingPeriod]}
                  </span>
                  <span className="ml-2 self-end pb-2 text-sm text-slate-500">
                    /{pricingPeriod === 'monthly' ? 'Monthly' : 'Yearly'}
                  </span>
                </div>

                <p className="mb-8 text-sm text-slate-600">{plan.description}</p>

                <div className="mb-8">
                  <p className="mb-4 text-sm font-semibold text-slate-900">
                    {plan.name} plan includes:
                  </p>
                  <ul className="space-y-3">
                    {plan.features.map((feature, featureIdx) => (
                      <li key={featureIdx} className="flex items-start gap-3 text-sm text-slate-600">
                        <img src="assets/images/v3/check.png" alt="" className="mt-0.5 h-5 w-5 flex-shrink-0" />
                        <span>{feature}</span>
                      </li>
                    ))}
                  </ul>
                </div>

                <a
                  href={register().url}
                  className={`block w-full rounded-full py-3 text-center text-sm font-semibold transition-all ${
                    plan.isPopular
                      ? 'bg-indigo-600 text-white hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-600/30'
                      : 'border border-slate-200 bg-white text-slate-900 hover:border-indigo-600 hover:text-indigo-600'
                  }`}
                >
                  Try it for free
                </a>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Video & Testimonial Section */}
      <section className="bg-slate-50 py-20 lg:py-28">
        <div className="container mx-auto px-4">
          <div className="mb-16 flex flex-col items-start justify-between gap-6 lg:flex-row lg:items-center">
            <div className="max-w-2xl">
              <h2 className="font-raleway text-4xl font-bold tracking-tight text-slate-900 lg:text-5xl">
                Integration with dozens of tools
              </h2>
            </div>
            <a
              href="#"
              className="rounded-full bg-indigo-600 px-8 py-3 text-sm font-semibold text-white transition-all hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-600/30"
            >
              Browse all integrations
            </a>
          </div>

          <div className="grid gap-12 lg:grid-cols-2">
            <div className="relative overflow-hidden rounded-2xl">
              <img
                src="assets/images/v3/video-bg.png"
                alt="Video"
                className="w-full"
              />
              <button className="absolute left-1/2 top-1/2 flex h-20 w-20 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white shadow-2xl transition-transform hover:scale-110">
                <Play className="ml-1 h-8 w-8 fill-indigo-600 text-indigo-600" />
              </button>
            </div>

            <div className="flex flex-col justify-center">
              <div className="mb-6 flex gap-1">
                {[...Array(5)].map((_, i) => (
                  <img key={i} src="assets/images/v3/star.png" alt="" className="h-6 w-6" />
                ))}
              </div>
              <p className="mb-6 text-lg leading-relaxed text-slate-600">
                "Our team's productivity grow up after implementing this SaaS tool. The intuitive interface & seamless collaboration features made a significant impact on our workflow. Game-changer for our company efficiency!"
              </p>
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-raleway font-semibold text-slate-900">Jonas Aly</p>
                  <p className="text-sm text-slate-500">Founder @ Sitemark</p>
                </div>
                <img src="assets/images/v2/b_v2_5.png" alt="" className="h-8" />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* FAQ Section */}
      <section id="faq" className="py-20 lg:py-28">
        <div className="container mx-auto px-4">
          <div className="mb-12 text-center">
            <h2 className="font-raleway text-4xl font-bold tracking-tight text-slate-900 lg:text-5xl">
              Find all the answers to your confusion
            </h2>
          </div>

          <div className="mx-auto max-w-3xl space-y-4">
            {faqs.map((faq, idx) => (
              <div
                key={idx}
                className="overflow-hidden rounded-2xl border border-slate-200 bg-white transition-all hover:shadow-md"
              >
                <button
                  onClick={() => setExpandedFaq(expandedFaq === idx ? -1 : idx)}
                  className="flex w-full items-center justify-between p-6 text-left transition-colors hover:bg-slate-50"
                >
                  <span className="font-raleway font-semibold text-slate-900">
                    {faq.question}
                  </span>
                  <div className="flex-shrink-0">
                    {expandedFaq === idx ? (
                      <img src="assets/images/v3/minus.png" alt="" className="h-6 w-6" />
                    ) : (
                      <img src="assets/images/v3/plus.png" alt="" className="h-6 w-6" />
                    )}
                  </div>
                </button>
                {expandedFaq === idx && (
                  <div className="border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <p className="text-sm leading-relaxed text-slate-600">{faq.answer}</p>
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Footer CTA */}
      <section className="bg-slate-900 py-20 text-white">
        <div className="container mx-auto px-4">
          <div className="flex flex-col items-center justify-between gap-8 lg:flex-row">
            <h2 className="font-raleway text-4xl font-bold tracking-tight lg:text-5xl">
              Take your startup to the next level
            </h2>
            <a
              href={register().url}
              className="rounded-full bg-indigo-600 px-8 py-3 text-sm font-semibold text-white transition-all hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-600/30"
            >
              Get started now
            </a>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-slate-900 py-16 text-white">
        <div className="container mx-auto px-4">
          <div className="grid gap-12 lg:grid-cols-12">
            <div className="lg:col-span-4">
              <img src="/SnapdraftLogoWhite.svg" alt="SnapDraft" className="mb-6 h-8" />
              <p className="mb-6 text-sm leading-relaxed text-slate-400">
                We're your innovation partner, delivering cutting-edge solutions that elevate your business to the next level.
              </p>
              <div className="flex gap-3">
                <a
                  href="#"
                  className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-800 text-slate-400 transition-colors hover:bg-indigo-600 hover:text-white"
                >
                  <i className="fab fa-twitter"></i>
                </a>
                <a
                  href="#"
                  className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-800 text-slate-400 transition-colors hover:bg-indigo-600 hover:text-white"
                >
                  <i className="fab fa-facebook-f"></i>
                </a>
                <a
                  href="#"
                  className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-800 text-slate-400 transition-colors hover:bg-indigo-600 hover:text-white"
                >
                  <i className="fab fa-linkedin"></i>
                </a>
                <a
                  href="#"
                  className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-800 text-slate-400 transition-colors hover:bg-indigo-600 hover:text-white"
                >
                  <i className="fab fa-github"></i>
                </a>
              </div>
            </div>

            <div className="lg:col-span-3">
              <h3 className="mb-4 font-raleway font-semibold">Navigation</h3>
              <ul className="space-y-3 text-sm text-slate-400">
                <li><a href="#" className="transition-colors hover:text-white">Demos</a></li>
                <li><a href="#" className="transition-colors hover:text-white">About Us</a></li>
                <li><a href="#" className="transition-colors hover:text-white">Services</a></li>
                <li><a href="#" className="transition-colors hover:text-white">Pages</a></li>
                <li><a href="#" className="transition-colors hover:text-white">Contact</a></li>
              </ul>
            </div>

            <div className="lg:col-span-2">
              <h3 className="mb-4 font-raleway font-semibold">Utility pages</h3>
              <ul className="space-y-3 text-sm text-slate-400">
                <li><a href="#" className="transition-colors hover:text-white">Instructions</a></li>
                <li><a href="#" className="transition-colors hover:text-white">Style guide</a></li>
                <li><a href="#" className="transition-colors hover:text-white">Licenses</a></li>
                <li><a href="#" className="transition-colors hover:text-white">404 Not found</a></li>
                <li><a href="#" className="transition-colors hover:text-white">Password protected</a></li>
              </ul>
            </div>

            <div className="lg:col-span-3">
              <h3 className="mb-4 font-raleway font-semibold">Contact us</h3>
              <ul className="space-y-3 text-sm text-slate-400">
                <li className="flex items-center gap-3">
                  <img src="assets/images/icon/call.svg" alt="" className="h-5 w-5" />
                  <span>+088-234-6849</span>
                </li>
                <li className="flex items-center gap-3">
                  <img src="assets/images/icon/email.svg" alt="" className="h-5 w-5" />
                  <span>example@gmail.com</span>
                </li>
                <li className="flex items-start gap-3">
                  <img src="assets/images/icon/map.svg" alt="" className="mt-0.5 h-5 w-5" />
                  <span>Howard Street, 13125 USA</span>
                </li>
              </ul>
            </div>
          </div>

          <div className="mt-12 border-t border-slate-800 pt-8 text-center">
            <p className="text-sm text-slate-400">
              © Copyright 2024, All Rights Reserved by SnapDraft
            </p>
          </div>
        </div>
      </footer>

      {/* Custom Animations */}
      <style jsx>{`
        @keyframes float {
          0%, 100% {
            transform: translateY(0px);
          }
          50% {
            transform: translateY(-20px);
          }
        }
        
        .animate-float {
          animation: float 3s ease-in-out infinite;
        }
        
        .animation-delay-200 {
          animation-delay: 200ms;
        }
        
        .animation-delay-400 {
          animation-delay: 400ms;
        }
      `}</style>
    </>
  );
}
