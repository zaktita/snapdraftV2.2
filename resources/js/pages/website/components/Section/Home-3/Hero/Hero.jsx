/* eslint-disable react/no-unescaped-entities */
import { Check } from 'lucide-react';

const HeroSection = () => {
  return (
    <div className="pt-32 pb-20 px-4 bg-white min-h-screen flex items-center">
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

          {/* Email Subscription */}
          <div className="mb-8">
            <form className="flex flex-col sm:flex-row gap-3 max-w-md mx-auto mb-4" onSubmit={(e) => e.preventDefault()}>
              <input
                type="email"
                placeholder="Enter your e-mail address"
                className="flex-1 px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#f7931e]"
              />
              <button className="bg-[#f7931e] hover:bg-[#e68517] text-white px-8 py-3 rounded-lg font-semibold transition-colors">
                Get started
              </button>
            </form>
            
            {/* Checklist */}
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

          {/* Hero image placeholder with floating cards */}
          <div className="mt-16 relative max-w-2xl mx-auto">
            <div className="bg-gradient-to-br from-gray-100 to-gray-200 rounded-2xl aspect-video flex items-center justify-center border-2 border-gray-300">
              <svg className="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            {/* Floating cards */}
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
    </div>
  );
};

export default HeroSection;
