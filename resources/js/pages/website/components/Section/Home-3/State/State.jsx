"use client";
import { useEffect, useState } from "react";

const CountUpComponent = ({ end, duration }) => {
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

const StateSection = () => {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      const section = document.getElementById("zubuz-counter");
      if (section) {
        const rect = section.getBoundingClientRect();
        const isVisible = rect.top <= window.innerHeight && rect.bottom >= 0;
        setIsVisible(isVisible);
      }
    };

    window.addEventListener("scroll", handleScroll);

    return () => {
      window.removeEventListener("scroll", handleScroll);
    };
  }, []);

  return (
    <div className="py-20 px-4 bg-gray-900 text-white border-t border-gray-800">
      <div id="zubuz-counter"></div>
      <div className="max-w-6xl mx-auto">
        <div className="grid md:grid-cols-4 gap-8">
          <div className="text-center">
            <h3 className="text-5xl md:text-6xl font-bold mb-2">
              {isVisible ? <CountUpComponent end={99} duration={3} /> : 99}
              <span className="text-[#f7931e]">%</span>
            </h3>
            <p className="text-gray-400 text-lg">Positive user rating</p>
          </div>
          <div className="text-center">
            <h3 className="text-5xl md:text-6xl font-bold mb-2">
              {isVisible ? <CountUpComponent end={2} duration={3} /> : 2}
              <span className="text-[#f7931e]">K</span>
            </h3>
            <p className="text-gray-400 text-lg">Monthly active user</p>
          </div>
          <div className="text-center">
            <h3 className="text-5xl md:text-6xl font-bold mb-2">
              {isVisible ? <CountUpComponent end={100} duration={3} /> : 100}
              <span className="text-[#f7931e]">%</span>
            </h3>
            <p className="text-gray-400 text-lg">Uptime in the last year</p>
          </div>
          <div className="text-center">
            <h3 className="text-5xl md:text-6xl font-bold mb-2">
              {isVisible ? <CountUpComponent end={55} duration={3} /> : 55}
              <span className="text-[#f7931e]">+</span>
            </h3>
            <p className="text-gray-400 text-lg">Integration with other tools</p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default StateSection;
