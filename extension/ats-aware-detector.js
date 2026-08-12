(function exposeJobPilotAtsAwareDetector(root) {
  const detector = {
    detect(documentRef = document) {
      const generic = root.JobPilotFormDetector;
      if (!generic || typeof generic.detect !== 'function') {
        throw new Error('JobPilot generic form detector is unavailable.');
      }

      const detection = generic.detect(documentRef);
      const adapters = root.JobPilotAtsAdapters;
      if (!adapters || typeof adapters.enhanceDetection !== 'function') {
        return detection;
      }

      return adapters.enhanceDetection(detection, documentRef, documentRef.location);
    },
  };

  root.JobPilotAtsAwareDetector = detector;
  if (typeof module !== 'undefined' && module.exports) module.exports = detector;
})(typeof globalThis !== 'undefined' ? globalThis : this);
