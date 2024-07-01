package webrtc_signaling

import "nasotku/includes/api_root_classes"

const (
	DATA_TYPE_CONNECT                    = "connect"
	DATA_TYPE_OFFER                      = "offer"
	DATA_TYPE_ANSWER                     = "answer"
	DATA_TYPE_ICE_CANDIDATES             = "ice_candidate"
	DATA_TYPE_FINISH_SEND_ICE_CANDIDATES = "finish_send_ice_candidates"
)

/*type WebrtcSignalingOfferRequest struct {
	api_root_classes.MainRequestClass
	Offer string `json:"offer"`
}

type WebrtcSignalingOfferResponse struct {
	api_root_classes.MainResponseClass
	Offer string `json:"offer"`
}*/

type WebrtcSignalingRequest struct {
	api_root_classes.MainRequestClass
	DataType string `json:"dataType"`
	Data     string `json:"data"`
}

type WebrtcSignalingResponse struct {
	api_root_classes.MainResponseClass
	DataType string `json:"dataType"`
	Data     string `json:"data"`
}
