(function exposeJobPilotAtsAwareDetector(root) {
  const detector = {
    detect(documentRef = document) {
      const generic = root.JobPilotFormDetector;
      if (!generic || typeof generic.detect !== 'function') {
        throw new Error('JobPilot generic form detector is unavailable.');
      }

      let detection = generic.detect(documentRef);

      const adapters = root.JobPilotAtsAdapters;
      if (adapters && typeof adapters.enhanceDetection === 'function') {
        detection = adapters.enhanceDetection(detection, documentRef, documentRef.location);
      }

      const complexAdapters = root.JobPilotComplexAtsAdapters;
      if (complexAdapters && typeof complexAdapters.enhanceDetection === 'function') {
        detection = complexAdapters.enhanceDetection(detection, documentRef, documentRef.location);
      }

      return detection;
    },
  };

  root.JobPilotAtsAwareDetector = detector;
  if (typeof module !== 'undefined' && module.exports) module.exports = detector;
})(typeof globalThis !== 'undefined' ? globalThis : this);
