const ContentSection = () => {
  return (
    <div className="py-20 px-4 bg-white">
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
                <div className="w-12 h-12 rounded-lg bg-[#f7931e]/10 flex items-center justify-center flex-shrink-0 font-bold text-[#f7931e] text-lg">
                  ⚡
                </div>
                <div>
                  <h3 className="font-bold text-gray-900 mb-2">Fast implementation:</h3>
                  <p className="text-gray-600">Our SaaS solutions can be implemented quickly, often within a few clicks, reducing setup time and allowing startups immediately.</p>
                </div>
              </div>

              <div className="flex gap-4">
                <div className="w-12 h-12 rounded-lg bg-[#f7931e]/10 flex items-center justify-center flex-shrink-0 font-bold text-[#f7931e] text-lg">
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
    </div>
  );
};

export default ContentSection;
