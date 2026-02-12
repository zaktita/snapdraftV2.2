import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import '../../../css/landing.css';
import { cn } from '@/lib/utils';
import { 
  Check,
  ChevronDown,
  Menu,
  X,
  Play,
  Star,
  Phone,
  Mail,
  MapPin,
  Twitter,
  Facebook,
  Linkedin,
  Github,
} from 'lucide-react';

// Mock CountUp component for animations
const CountUp = ({ end, duration }: { end: number; duration: number }) => {
  const [count, setCount] = useState(0);

  useEffect(() => {
    let start = 0;
    const increment = end / (duration * 60);
    const timer = setInterval(() => {
      start += increment;
      if (start >= end) {
        setCount(end);
        clearInterval(timer);
      } else {
        setCount(Math.floor(start));
      }
    }, 1000 / 60);
    return () => clearInterval(timer);
  }, [end, duration]);

  return <span>{count}</span>;
};

export default function DesignTest() {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isMonthly, setIsMonthly] = useState(true);
  const [expandedFaq, setExpandedFaq] = useState<number | null>(0);
  const [isPopupOpen, setIsPopupOpen] = useState(false);
  const [scrollClassName, setScrollClassName] = useState('');

  useEffect(() => {
    const handleScroll = () => {
      if (window.scrollY > 100) {
        setScrollClassName('bg-white shadow-sm');
      } else {
        setScrollClassName('');
      }
    };

    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Service data from Zubaz
  const serviceData = [
    {
      title: 'Customer support',
      icon: '📞',
      description: 'Clients engagement platform that offers tools for ticketing.'
    },
    {
      title: 'Project management',
      icon: '📋',
      description: 'A project and task management tool that helps plan, organize.'
    },
    {
      title: 'Email marketing',
      icon: '✉️',
      description: 'A widely used email marketing platform with some features.'
    },
    {
      title: 'Human resources',
      icon: '👥',
      description: 'An all-in-one HR, payroll, and benefits platform designed.'
    },
    {
      title: 'Accounting and finance',
      icon: '💰',
      description: 'Accounting software that helps with invoicing financial report.'
    },
    {
      title: 'Cyber Security',
      icon: '🔒',
      description: 'Security platform that provide protection from cyber threat.'
    }
  ];

  // Pricing plans from Zubaz
  const pricingPlans = [
    {
      name: 'Free',
      monthlyPrice: 0,
      yearlyPrice: 19,
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
      monthlyPrice: 19,
      yearlyPrice: 39,
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
      monthlyPrice: 29,
      yearlyPrice: 59,
      description: 'This plan is geared toward growing businesses',
      highlighted: true,
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
      monthlyPrice: 59,
      yearlyPrice: 99,
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

  // FAQ from Zubaz
  const faqs = [
    {
      question: "Q: What makes your SaaS solution stand out from competitors?",
      answer: "Our SaaS platform distinguishes itself through a combination of user-friendly design, robust feature set, and a commitment to constant innovation. We prioritize customer feedback to ensure we stay ahead of the curve and meet evolving business needs."
    },
    {
      question: "Q: How secure is the data stored on your platform?",
      answer: "Security is our top priority. We employ state-of-the-art encryption, regular security audits, and compliance with industry standards to safeguard your data. Our commitment is to provide a secure environment for your valuable information."
    },
    {
      question: "Q: Can your SaaS solution integrate with other tools we use?",
      answer: "Absolutely. We understand the importance of seamless integration. Our SaaS solution is designed to work harmoniously with a variety of popular tools and platforms, ensuring a smooth workflow and reducing any disruptions to your current processes."
    },
    {
      question: "Q: What kind of customer support can we expect?",
      answer: "Our customer support team is dedicated to your success. You can expect prompt responses, helpful resources, and, if needed, personalized assistance. We believe in building long-lasting relationships with our users, and exceptional support."
    },
    {
      question: "Q: How does your pricing model work, and is there flexibility as our business grows?",
      answer: "We offer a range of flexible pricing plans to accommodate businesses of all sizes. Whether you're a startup or an enterprise, our pricing scales with your needs. You can choose from monthly or annual billing."
    }
  ];

  // Counter data from Zubaz State section
  const counters = [
    { number: 99.5, suffix: '%', label: 'Positive user rating' },
    { number: 2, suffix: 'K', label: 'Monthly active user' },
    { number: 100, suffix: '%', label: 'Uptime in the last year' },
    { number: 55, suffix: '+', label: 'Integration with other tools' }
  ];

  return (
    <>
      <Head title="Design Test - SnapDraft" />
      
      {/* HEADER - Zubaz inspired */}
      <header className={cn(
        'fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-white',
        scrollClassName
      )}>
        <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
          <div className="font-bold text-2xl text-gray-900">SnapDraft</div>
          
          {/* Desktop Menu */}
          <nav className="hidden md:flex items-center gap-8">
            <a href="#" className="text-gray-700 hover:text-[#f7931e] transition">Demo</a>
            <a href="#" className="text-gray-700 hover:text-[#f7931e] transition">About Us</a>
            <a href="#" className="text-gray-700 hover:text-[#f7931e] transition">Pages</a>
            <a href="#" className="text-gray-700 hover:text-[#f7931e] transition">Blog</a>
            <a href="#" className="text-gray-700 hover:text-[#f7931e] transition">Contact Us</a>
          </nav>

          <div className="hidden md:flex gap-4">
            <Button variant="ghost" className="text-gray-700">Login</Button>
            <Button className="bg-[#f7931e] hover:bg-[#e68517] text-white">
              Get Started
            </Button>
          </div>

          {/* Mobile Menu Button */}
          <button 
            className="md:hidden"
            onClick={() => setIsMenuOpen(!isMenuOpen)}
          >
            {isMenuOpen ? <X /> : <Menu />}
          </button>
        </div>
      </header>

      {/* 1. HERO SECTION - Exact Zubaz content */}
      <section className="pt-32 pb-20 px-4 bg-white min-h-screen flex items-center">
        <div className="max-w-6xl mx-auto w-full">
          <div className="text-center mb-12">
            <h1 className="text-5xl md:text-6xl font-bold text-gray-900 mb-6 leading-tight">
              Launch your startup's success with SaaS
              <span className="relative inline-block ml-2">
                <span className="text-[#f7931e]">✨</span>
              </span>
            </h1>
            <p className="text-xl text-gray-600 mb-12 max-w-2xl mx-auto">
              Our SaaS for startup businesses offers a cost-effective, scalable and user-friendly approach to access and use the software tools they need and provides powerful technology solutions.
            </p>

            {/* Email Subscription - Zubaz style */}
            <div className="mb-8">
              <form className="flex flex-col sm:flex-row gap-3 max-w-md mx-auto mb-4" onSubmit={(e) => e.preventDefault()}>
                <input
                  type="email"
                  placeholder="Enter your e-mail address"
                  className="flex-1 px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#f7931e]"
                />
                <Button className="bg-[#f7931e] hover:bg-[#e68517] text-white px-8 rounded-lg">
                  Get started
                </Button>
              </form>
              
              {/* Zubaz-style checklist */}
              <div className="flex flex-col sm:flex-row gap-6 justify-center text-sm">
                <div className="flex items-center gap-2 text-gray-700">
                  <Check className="w-5 h-5 text-[#f7931e]" />
                  No credit card required
                </div>
                <div className="flex items-center gap-2 text-gray-700">
                  <Check className="w-5 h-5 text-[#f7931e]" />
                  7-day free trial
                </div>
              </div>
            </div>

            {/* Hero image placeholder with floating cards - Zubaz style */}
            <div className="mt-16 relative max-w-2xl mx-auto">
              <div className="bg-gradient-to-br from-gray-100 to-gray-200 rounded-2xl aspect-video flex items-center justify-center border-2 border-gray-300">
                <Play className="w-16 h-16 text-gray-400" />
              </div>
              {/* Floating cards like Zubaz */}
              <div className="absolute -top-4 -left-4 w-24 h-24 bg-white rounded-lg shadow-lg border border-gray-200 flex items-center justify-center">
                <div className="text-center">
                  <div className="text-2xl font-bold text-gray-900">99%</div>
                  <div className="text-xs text-gray-600">Uptime</div>
                </div>
              </div>
              <div className="absolute -bottom-4 -right-4 w-24 h-24 bg-white rounded-lg shadow-lg border border-gray-200 flex items-center justify-center">
                <div className="text-center">
                  <div className="text-2xl font-bold text-[#f7931e]">24/7</div>
                  <div className="text-xs text-gray-600">Support</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 2. SERVICE SECTION - Exact Zubaz content */}
      <section className="py-20 px-4 bg-gray-50 border-t border-gray-200">
        <div className="max-w-6xl mx-auto">
          <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-8 mb-16">
            <div>
              <h2 className="text-4xl md:text-5xl font-bold text-gray-900">
                Wide range of SaaS solutions
              </h2>
            </div>
            <Button className="bg-[#f7931e] hover:bg-[#e68517] text-white px-8 py-6 rounded-full">
              View all services
            </Button>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            {serviceData.map((service, idx) => (
              <div key={idx} className="sd-card bg-white p-8 rounded-xl hover:shadow-xl transition-all">
                <div className="text-5xl mb-4">{service.icon}</div>
                <h3 className="text-xl font-bold text-gray-900 mb-3">{service.title}</h3>
                <p className="text-gray-600">{service.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* 3. CONTENT SECTION - Zubaz style with image + features */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-6xl mx-auto">
          <div className="grid md:grid-cols-2 gap-12 items-center">
            {/* Image */}
            <div className="flex items-center justify-center">
              <div className="relative w-full max-w-sm">
                <div className="bg-gradient-to-br from-[#f7931e]/20 to-gray-200 rounded-2xl aspect-square flex items-center justify-center border-2 border-dashed border-gray-300">
                  <div className="text-center">
                    <div className="text-6xl mb-4">📊</div>
                    <div className="text-gray-600">Visual Content</div>
                  </div>
                </div>
                <div className="absolute -bottom-6 -right-6 w-32 h-32 bg-white rounded-lg shadow-lg p-4 border border-gray-200">
                  <div className="text-sm font-semibold text-gray-900 mb-2">Dashboard</div>
                  <div className="w-full h-16 bg-gray-100 rounded flex items-center justify-center text-xs text-gray-600">
                    Analytics
                  </div>
                </div>
              </div>
            </div>

            {/* Features */}
            <div>
              <h2 className="text-4xl font-bold text-gray-900 mb-6">
                Agility to adapt to market needs
              </h2>
              <p className="text-lg text-gray-600 mb-8">
                Startups often need to bring their products or services to market quickly. Our SaaS applications are readily available and reducing development and deployment time.
              </p>

              <div className="space-y-6">
                <div className="flex gap-4">
                  <div className="w-12 h-12 rounded-lg bg-[#f7931e]/10 flex items-center justify-center flex-shrink-0 font-bold text-[#f7931e]">
                    ⚡
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900 mb-2">Fast implementation:</h3>
                    <p className="text-gray-600">Our SaaS solutions can be implemented quickly, often within a few clicks, reducing setup time and allowing startups immediately.</p>
                  </div>
                </div>

                <div className="flex gap-4">
                  <div className="w-12 h-12 rounded-lg bg-[#f7931e]/10 flex items-center justify-center flex-shrink-0 font-bold text-[#f7931e]">
                    🚀
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900 mb-2">Accelerated time-to-market:</h3>
                    <p className="text-gray-600">SaaS tools startups can accelerate their product or service launch and stay ahead of the competition and reach customers faster.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 4. CONTENT-2 SECTION - Dark background with numbered features */}
      <section className="py-20 px-4 bg-gray-900 text-white">
        <div className="max-w-6xl mx-auto">
          <div className="grid md:grid-cols-2 gap-12 items-center">
            {/* Image - right side on desktop */}
            <div className="md:order-2 flex items-center justify-center">
              <div className="relative w-full max-w-sm">
                <div className="bg-gradient-to-br from-gray-800 to-gray-700 rounded-2xl aspect-square flex items-center justify-center border-2 border-dashed border-gray-600">
                  <div className="text-center">
                    <div className="text-6xl mb-4">💳</div>
                    <div className="text-gray-400">Payment System</div>
                  </div>
                </div>
                <div className="absolute -bottom-6 -left-6 w-32 h-24 bg-gray-800 rounded-lg shadow-lg p-4 border border-gray-600">
                  <div className="text-xs font-semibold text-gray-300 mb-2">Secure Checkout</div>
                  <div className="space-y-1">
                    <div className="h-2 bg-gray-600 rounded w-full"></div>
                    <div className="h-2 bg-gray-600 rounded w-3/4"></div>
                  </div>
                </div>
              </div>
            </div>

            {/* Content - left side */}
            <div className="md:order-1">
              <h2 className="text-4xl font-bold mb-6">
                Cost-effective & simple process
              </h2>

              <div className="space-y-6">
                <div className="flex gap-4">
                  <div className="w-10 h-10 rounded-lg bg-[#f7931e] flex items-center justify-center font-bold text-white flex-shrink-0">
                    1
                  </div>
                  <div>
                    <h3 className="font-bold mb-2">Subscription-based model:</h3>
                    <p className="text-gray-300">
                      Our SaaS operates on a subscription-based payment structure, which allows users to pay for the software on a regular basis.
                    </p>
                  </div>
                </div>

                <div className="flex gap-4">
                  <div className="w-10 h-10 rounded-lg bg-[#f7931e] flex items-center justify-center font-bold text-white flex-shrink-0">
                    2
                  </div>
                  <div>
                    <h3 className="font-bold mb-2">Cloud hosting:</h3>
                    <p className="text-gray-300">
                      SaaS applications are hosted on cloud servers, eliminating the need for users to manage their own infrastructure costs.
                    </p>
                  </div>
                </div>

                <div className="flex gap-4">
                  <div className="w-10 h-10 rounded-lg bg-[#f7931e] flex items-center justify-center font-bold text-white flex-shrink-0">
                    3
                  </div>
                  <div>
                    <h3 className="font-bold mb-2">Automated updates and maintenance:</h3>
                    <p className="text-gray-300">
                      Manage software updates, security patches and maintenance so that users always have access to the latest features and secure.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 5. STATE/COUNTER SECTION - Zubaz metrics */}
      <section className="py-20 px-4 bg-gray-900 text-white border-t border-gray-800">
        <div className="max-w-6xl mx-auto">
          <div className="grid md:grid-cols-4 gap-8">
            {counters.map((counter, idx) => (
              <div key={idx} className="text-center">
                <h3 className="text-5xl md:text-6xl font-bold mb-2">
                  <CountUp end={counter.number} duration={3} />
                  <span className="text-[#f7931e]">{counter.suffix}</span>
                </h3>
                <p className="text-gray-400 text-lg">{counter.label}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* 6. PRICING SECTION - Exact Zubaz pricing */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
              Rational planning for rapid growth
            </h2>

            {/* Toggle Monthly/Yearly */}
            <div className="flex justify-center items-center gap-6">
              <span className={cn('font-semibold', isMonthly ? 'text-gray-900' : 'text-gray-500')}>
                Monthly
              </span>
              <button
                onClick={() => setIsMonthly(!isMonthly)}
                className={cn(
                  'relative w-14 h-7 rounded-full transition-all',
                  isMonthly ? 'bg-gray-300' : 'bg-[#f7931e]'
                )}
              >
                <div className={cn(
                  'absolute top-1 w-5 h-5 bg-white rounded-full transition-transform',
                  isMonthly ? 'left-1' : 'right-1'
                )}></div>
              </button>
              <span className={cn('font-semibold', !isMonthly ? 'text-gray-900' : 'text-gray-500')}>
                Annually
              </span>
            </div>
          </div>

          {/* Pricing Cards - Zubaz exact */}
          <div className="grid md:grid-cols-4 gap-6">
            {pricingPlans.map((plan, idx) => (
              <div
                key={idx}
                className={cn(
                  'rounded-xl p-8 transition-all border',
                  plan.highlighted
                    ? 'bg-gradient-to-br from-[#f7931e] to-[#e68517] text-white border-[#f7931e] shadow-2xl scale-105'
                    : 'bg-white text-gray-900 border-gray-200 hover:border-[#f7931e]'
                )}
              >
                <h3 className="text-2xl font-bold mb-2">{plan.name}</h3>
                <p className={cn('mb-6 text-sm', plan.highlighted ? 'text-orange-100' : 'text-gray-600')}>
                  {plan.description}
                </p>

                <div className="mb-8">
                  <span className="text-4xl font-bold">
                    ${isMonthly ? plan.monthlyPrice : plan.yearlyPrice}
                  </span>
                  <p className="text-sm mt-2">
                    /{isMonthly ? 'Monthly' : 'Yearly'}
                  </p>
                </div>

                <Button className={cn(
                  'w-full mb-8 font-semibold py-6 rounded-lg',
                  plan.highlighted
                    ? 'bg-white text-[#f7931e] hover:bg-gray-100'
                    : 'bg-[#f7931e] text-white hover:bg-[#e68517]'
                )}>
                  Try it for free
                </Button>

                <div className="space-y-4">
                  {plan.features.map((feature, i) => (
                    <div key={i} className="flex gap-3 items-start">
                      <Check className={cn(
                        'w-5 h-5 flex-shrink-0 mt-0.5',
                        plan.highlighted ? 'text-white' : 'text-[#f7931e]'
                      )} />
                      <span className="text-sm">{feature}</span>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* 7. INTEGRATIONS SECTION - Zubaz video + testimonial */}
      <section className="py-20 px-4 bg-gray-50">
        <div className="max-w-6xl mx-auto">
          <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-8 mb-16">
            <div>
              <h2 className="text-4xl md:text-5xl font-bold text-gray-900">
                Integration with dozens of tools
              </h2>
            </div>
            <Button className="bg-[#f7931e] hover:bg-[#e68517] text-white px-8 py-6 rounded-full">
              Browse all integrations
            </Button>
          </div>

          <div className="grid md:grid-cols-2 gap-12 items-center">
            {/* Video */}
            <div className="relative">
              <div className="bg-gradient-to-br from-gray-300 to-gray-400 rounded-2xl aspect-video flex items-center justify-center cursor-pointer hover:from-gray-400 hover:to-gray-500 transition-all"
                   onClick={() => setIsPopupOpen(true)}>
                <button className="absolute w-16 h-16 rounded-full bg-[#f7931e] flex items-center justify-center hover:bg-[#e68517] transition-all shadow-lg">
                  <Play className="w-8 h-8 text-white fill-white" />
                  <div className="absolute inset-0 rounded-full border-4 border-[#f7931e]/30 animate-pulse"></div>
                </button>
              </div>
            </div>

            {/* Testimonial */}
            <div>
              <div className="flex gap-1 mb-6">
                {[...Array(5)].map((_, i) => (
                  <Star key={i} className="w-5 h-5 fill-[#f7931e] text-[#f7931e]" />
                ))}
              </div>
              <p className="text-lg text-gray-700 mb-6 leading-relaxed">
                "Our team's productivity grow up after implementing this SaaS tool. The intuitive interface & seamless collaboration features made a significant impact on our workflow. Game-changer for our company efficiency!"
              </p>
              <div>
                <p className="font-bold text-gray-900">Jonas Aly</p>
                <p className="text-gray-600">Founder @ Sitemark</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 8. FAQ SECTION - Exact Zubaz accordion */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-3xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-4xl md:text-5xl font-bold text-gray-900">
              Find all the answers to your confusion
            </h2>
          </div>

          <div className="space-y-4">
            {faqs.map((faq, idx) => (
              <div
                key={idx}
                className="bg-white rounded-lg border border-gray-200 overflow-hidden transition-all hover:border-[#f7931e]"
              >
                <button
                  onClick={() => setExpandedFaq(expandedFaq === idx ? null : idx)}
                  className="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                  <p className="font-semibold text-gray-900 text-left text-sm md:text-base">{faq.question}</p>
                  <ChevronDown className={cn(
                    'w-5 h-5 text-[#f7931e] flex-shrink-0 transition-transform',
                    expandedFaq === idx && 'rotate-180'
                  )} />
                </button>

                {expandedFaq === idx && (
                  <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <p className="text-gray-700 leading-relaxed">{faq.answer}</p>
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* 9. CTA SECTION */}
      <section className="py-20 px-4 bg-gray-50">
        <div className="max-w-4xl mx-auto text-center">
          <h2 className="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
            Take your startup to the next level
          </h2>
          <Button className="bg-[#f7931e] hover:bg-[#e68517] text-white px-8 py-6 text-lg rounded-full">
            Get started now
          </Button>
        </div>
      </section>

      {/* 10. FOOTER SECTION - Exact Zubaz footer */}
      <footer className="bg-gray-900 text-white py-20 px-4">
        <div className="max-w-6xl mx-auto">
          {/* Footer Top CTA */}
          <div className="mb-16 pb-16 border-b border-gray-800">
            <div className="grid md:grid-cols-2 gap-12 items-center">
              <div>
                <h3 className="text-3xl md:text-4xl font-bold mb-4">
                  Take your startup to the next level
                </h3>
              </div>
              <div className="flex justify-start md:justify-end">
                <Button className="bg-[#f7931e] hover:bg-[#e68517] text-white px-8 py-6 rounded-full">
                  Get started now
                </Button>
              </div>
            </div>
          </div>

          {/* Footer Content */}
          <div className="grid md:grid-cols-4 gap-12 mb-12">
            {/* Brand */}
            <div>
              <h3 className="font-bold text-xl mb-4">SnapDraft</h3>
              <p className="text-gray-400 mb-6">
                We're your innovation partner, delivering cutting-edge solutions that elevate your business to the next level.
              </p>
              <div className="flex gap-4">
                <a href="#" className="text-gray-400 hover:text-[#f7931e] transition"><Twitter className="w-5 h-5" /></a>
                <a href="#" className="text-gray-400 hover:text-[#f7931e] transition"><Facebook className="w-5 h-5" /></a>
                <a href="#" className="text-gray-400 hover:text-[#f7931e] transition"><Linkedin className="w-5 h-5" /></a>
                <a href="#" className="text-gray-400 hover:text-[#f7931e] transition"><Github className="w-5 h-5" /></a>
              </div>
            </div>

            {/* Navigation */}
            <div>
              <h4 className="font-bold mb-6">Navigation</h4>
              <ul className="space-y-3 text-gray-400">
                <li><a href="#" className="hover:text-[#f7931e] transition">Demos</a></li>
                <li><a href="#" className="hover:text-[#f7931e] transition">About Us</a></li>
                <li><a href="#" className="hover:text-[#f7931e] transition">Services</a></li>
                <li><a href="#" className="hover:text-[#f7931e] transition">Pages</a></li>
                <li><a href="#" className="hover:text-[#f7931e] transition">Contact</a></li>
              </ul>
            </div>

            {/* Utility Pages */}
            <div>
              <h4 className="font-bold mb-6">Utility pages</h4>
              <ul className="space-y-3 text-gray-400">
                <li><a href="#" className="hover:text-[#f7931e] transition">Instructions</a></li>
                <li><a href="#" className="hover:text-[#f7931e] transition">Style guide</a></li>
                <li><a href="#" className="hover:text-[#f7931e] transition">Licenses</a></li>
                <li><a href="#" className="hover:text-[#f7931e] transition">404 Not found</a></li>
                <li><a href="#" className="hover:text-[#f7931e] transition">Password protected</a></li>
              </ul>
            </div>

            {/* Contact */}
            <div>
              <h4 className="font-bold mb-6">Contact us</h4>
              <ul className="space-y-3">
                <li className="flex items-center gap-2 text-gray-400 hover:text-[#f7931e] transition">
                  <Phone className="w-4 h-4" />
                  <a href="tel:+088-234-6849">+088-234-6849</a>
                </li>
                <li className="flex items-center gap-2 text-gray-400 hover:text-[#f7931e] transition">
                  <Mail className="w-4 h-4" />
                  <a href="mailto:example@gmail.com">example@gmail.com</a>
                </li>
                <li className="flex items-center gap-2 text-gray-400 hover:text-[#f7931e] transition">
                  <MapPin className="w-4 h-4" />
                  <a href="#">Howard Street, 13125 USA</a>
                </li>
              </ul>
            </div>
          </div>

          {/* Footer Bottom */}
          <div className="border-t border-gray-800 pt-8 text-center text-gray-400">
            <p>© Copyright 2026, All Rights Reserved by SnapDraft</p>
          </div>
        </div>
      </footer>

      {/* Video Popup Modal */}
      {isPopupOpen && (
        <div 
          className="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4"
          onClick={() => setIsPopupOpen(false)}
        >
          <div 
            className="relative w-full max-w-2xl aspect-video"
            onClick={(e) => e.stopPropagation()}
          >
            <iframe
              className="w-full h-full rounded-lg"
              src="https://www.youtube.com/embed/SixdAQtWJQ8?si=iDeCVOUMxo5bqmy9&autoplay=1"
              title="YouTube video player"
              frameBorder="0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
            ></iframe>
            <button 
              onClick={() => setIsPopupOpen(false)}
              className="absolute -top-10 right-0 text-white hover:text-[#f7931e] transition"
            >
              <X className="w-8 h-8" />
            </button>
          </div>
        </div>
      )}
    </>
  );
}
