"use client";
import { Link } from '@inertiajs/react';
import { useState, useEffect } from "react";

const ServiceSection = () => {
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

  return (
    <div className="py-20 px-4 bg-gray-50 border-t border-gray-200">
      <div className="max-w-6xl mx-auto">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-8 mb-16">
          <div>
            <h2 className="text-4xl md:text-5xl font-bold text-gray-900">
              Wide range of SaaS solutions
            </h2>
          </div>
          <button className="bg-[#f7931e] hover:bg-[#e68517] text-white px-8 py-3 rounded-full font-semibold transition-colors whitespace-nowrap">
            View all services
          </button>
        </div>

        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
          {serviceData.map((service, idx) => (
            <div key={idx} className="bg-white p-8 rounded-xl hover:shadow-xl transition-all">
              <div className="text-5xl mb-4">{service.icon}</div>
              <h3 className="text-xl font-bold text-gray-900 mb-3">{service.title}</h3>
              <p className="text-gray-600">{service.description}</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default ServiceSection;
