/* eslint-disable react/no-unescaped-entities */

import { Link } from '@inertiajs/react';
import {
  Facebook,
  Github,
  Instagram,
  Linkedin,
  Twitter,
  Phone,
  Mail,
  MapPin,
} from 'lucide-react';

const FooterSectionThree = () => {
  return (
    <footer className="bg-gray-900 text-white py-16">
      <div className="container max-w-6xl mx-auto px-4">
        {/* Footer Top Banner */}
        <div className="mb-16 pb-16 border-b border-gray-700">
          <div className="grid md:grid-cols-2 gap-8 items-center">
            <div>
              <h2 className="text-3xl md:text-4xl font-bold">Take your startup to the next level</h2>
            </div>
            <div className="flex justify-start md:justify-end">
              <Link className="bg-[#f7931e] hover:bg-[#e68517] text-white px-8 py-4 rounded-full font-semibold" href="/contact-us">
                <span>Get started now</span>
              </Link>
            </div>
          </div>
        </div>

        {/* Footer Content */}
        <div className="grid md:grid-cols-4 gap-12 mb-12">
          {/* Logo and Description */}
          <div className="md:col-span-1">
            <div className="mb-6">
              <Link href="/">
                <span className="text-2xl font-bold text-white">SnapDraft</span>
              </Link>
            </div>
            <p className="text-gray-400 mb-6">
              We're your innovation partner, delivering cutting-edge
              solutions that elevate your business to the next level.
            </p>
            <div className="flex gap-4">
              <a href="https://twitter.com/" target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-[#f7931e]">
                <Twitter size={20} />
              </a>
              <a href="https://facebook.com/" target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-[#f7931e]">
                <Facebook size={20} />
              </a>
              <a href="https://www.linkedin.com/" target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-[#f7931e]">
                <Linkedin size={20} />
              </a>
              <a href="https://github.com/" target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-[#f7931e]">
                <Github size={20} />
              </a>
            </div>
          </div>

          {/* Navigation */}
          <div>
            <h3 className="text-white font-semibold mb-6">Navigation</h3>
            <ul className="space-y-3">
              <li>
                <Link href="#" className="text-gray-400 hover:text-[#f7931e]">Demos</Link>
              </li>
              <li>
                <Link href="/about-us" className="text-gray-400 hover:text-[#f7931e]">About Us</Link>
              </li>
              <li>
                <Link href="#" className="text-gray-400 hover:text-[#f7931e]">Services</Link>
              </li>
              <li>
                <Link href="#" className="text-gray-400 hover:text-[#f7931e]">Pages</Link>
              </li>
              <li>
                <Link href="/contact-us" className="text-gray-400 hover:text-[#f7931e]">Contact</Link>
              </li>
            </ul>
          </div>

          {/* Utility Pages */}
          <div>
            <h3 className="text-white font-semibold mb-6">Utility Pages</h3>
            <ul className="space-y-3">
              <li>
                <Link href="#" className="text-gray-400 hover:text-[#f7931e]">Instructions</Link>
              </li>
              <li>
                <Link href="#" className="text-gray-400 hover:text-[#f7931e]">Style guide</Link>
              </li>
              <li>
                <Link href="#" className="text-gray-400 hover:text-[#f7931e]">Licenses</Link>
              </li>
              <li>
                <Link href="#" className="text-gray-400 hover:text-[#f7931e]">404 Not found</Link>
              </li>
              <li>
                <Link href="#" className="text-gray-400 hover:text-[#f7931e]">Password protected</Link>
              </li>
            </ul>
          </div>

          {/* Contact Us */}
          <div>
            <h3 className="text-white font-semibold mb-6">Contact Us</h3>
            <ul className="space-y-4">
              <li className="flex items-center gap-3 text-gray-400 hover:text-[#f7931e]">
                <Phone size={18} />
                <a href="tel:+088-234-6849">+088-234-6849</a>
              </li>
              <li className="flex items-center gap-3 text-gray-400 hover:text-[#f7931e]">
                <Mail size={18} />
                <a href="mailto:example@gmail.com">example@gmail.com</a>
              </li>
              <li className="flex items-start gap-3 text-gray-400 hover:text-[#f7931e]">
                <MapPin size={18} className="mt-1" />
                <span>Howard Street, 13125 USA</span>
              </li>
            </ul>
          </div>
        </div>

        {/* Footer Bottom */}
        <div className="pt-8 border-t border-gray-700 text-center text-gray-400">
          <p>&copy; Copyright 2024, All Rights Reserved by SnapDraft</p>
        </div>
      </div>
    </footer>
  );
};

export default FooterSectionThree;
