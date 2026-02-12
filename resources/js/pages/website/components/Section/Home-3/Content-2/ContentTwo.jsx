const ContentSectionTwo = () => {
  return (
    <div className="py-20 px-4 bg-gray-900 text-white">
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
    </div>
  );
};

export default ContentSectionTwo;
