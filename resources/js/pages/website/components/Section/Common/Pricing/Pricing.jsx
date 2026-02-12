"use client";

import { useState } from "react";
import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

const PricingSection = () => {
  const [isMonthly, setIsMonthly] = useState(true);

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

  return (
    <div className="py-20 px-4 bg-white">
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

        {/* Pricing Cards */}
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

              <button className={cn(
                'w-full mb-8 font-semibold py-3 px-4 rounded-lg transition-colors',
                plan.highlighted
                  ? 'bg-white text-[#f7931e] hover:bg-gray-100'
                  : 'bg-[#f7931e] text-white hover:bg-[#e68517]'
              )}>
                Try it for free
              </button>

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
    </div>
  );
};

export default PricingSection;
