/* eslint-disable react/no-unescaped-entities */

import { Link } from '@inertiajs/react';
import { useState } from "react";
import { X, Play, Star } from 'lucide-react';

const IntegrationSectionTwo = () => {
  const [popup, setPopup] = useState(false);

  const openPopup = () => {
    setPopup(true);
  };

  const closePopup = () => {
    setPopup(false);
  };

  return (
    <>
      <div className="py-20 px-4 bg-gray-50">
        <div className="max-w-6xl mx-auto">
          {/* Section Title */}
          <div className="mb-16">
            <div className="grid md:grid-cols-2 gap-8 items-center">
              <div>
                <h2 className="text-4xl md:text-5xl font-bold text-gray-900">Integration with dozens of tools</h2>
              </div>
              <div className="flex justify-start md:justify-end">
                <Link className="bg-[#f7931e] hover:bg-[#e68517] text-white px-8 py-4 rounded-full font-semibold" href="/integrations">
                  <span>Browse all integrations</span>
                </Link>
              </div>
            </div>
          </div>

          {/* Video and Testimonial Section */}
          <div className="grid md:grid-cols-2 gap-12 items-center">
            {/* Video Section */}
            <div>
              <div className="relative rounded-lg overflow-hidden bg-gray-900 aspect-video cursor-pointer" onClick={openPopup}>
                <div className="absolute inset-0 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center">
                  <button
                    className="relative group"
                    onClick={openPopup}
                  >
                    <div className="w-20 h-20 bg-[#f7931e] rounded-full flex items-center justify-center hover:bg-[#e68517] transition">
                      <Play size={32} className="text-white ml-1" fill="white" />
                    </div>
                    <div className="absolute inset-0 w-20 h-20 rounded-full border-2 border-[#f7931e] animate-pulse"></div>
                  </button>
                </div>
              </div>
            </div>

            {/* Testimonial Section */}
            <div>
              <div>
                <div className="flex gap-1 mb-6">
                  {[1, 2, 3, 4, 5].map((i) => (
                    <Star key={i} size={20} className="text-[#f7931e] fill-[#f7931e]" />
                  ))}
                </div>
                <p className="text-lg text-gray-700 mb-6 leading-relaxed">
                  "Our team's productivity grew up after implementing this
                  SaaS tool. The intuitive interface & seamless collaboration
                  features made a significant impact on our workflow.
                  Game-changer for our company efficiency!"
                </p>
                <div className="flex items-center gap-4">
                  <div>
                    <p className="font-semibold text-gray-900">Jonas Aly</p>
                    <span className="text-gray-600 text-sm">Founder @ Sitemark</span>
                  </div>
                  <img src="/images/v2/b_v2_5.png" alt="Sitemark" className="w-12 h-12 rounded-full object-cover" />
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Video Popup Modal */}
        {popup && (
          <div 
            className="fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4"
            onClick={closePopup}
          >
            <div 
              className="relative w-full max-w-4xl aspect-video"
              onClick={(e) => e.stopPropagation()}
            >
              <iframe
                className="w-full h-full rounded-lg"
                src="https://www.youtube.com/embed/SixdAQtWJQ8?si=TPxjQ04JgcZ5eEA9"
                title="YouTube video player"
                frameBorder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
              ></iframe>

              <button 
                className="absolute top-4 right-4 bg-white rounded-full p-2 hover:bg-gray-100 transition"
                onClick={closePopup}
              >
                <X size={24} className="text-gray-900" />
              </button>
            </div>
          </div>
        )}
      </div>
    </>
  );
};

export default IntegrationSectionTwo;
