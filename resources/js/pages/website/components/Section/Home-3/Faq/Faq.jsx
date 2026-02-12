/* eslint-disable react/no-unescaped-entities */
import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

const FaqSection = () => {
  const [expandedFaq, setExpandedFaq] = useState<number | null>(0);

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

  return (
    <div className="py-20 px-4 bg-white">
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
    </div>
  );
};

export default FaqSection;
